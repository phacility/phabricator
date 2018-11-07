<?php

/**
 * Select and lease tasks from the worker task queue.
 */
final class PhabricatorWorkerLeaseQuery extends PhabricatorQuery {

  const PHASE_LEASED = 'leased';
  const PHASE_UNLEASED = 'unleased';
  const PHASE_EXPIRED  = 'expired';

  private $ids;
  private $objectPHIDs;
  private $limit;
  private $skipLease;
  private $leased = false;

  public static function getDefaultWaitBeforeRetry() {
    return phutil_units('5 minutes in seconds');
  }

  public static function getDefaultLeaseDuration() {
    return phutil_units('2 hours in seconds');
  }

  /**
   * Set this flag to select tasks from the top of the queue without leasing
   * them.
   *
   * This can be used to show which tasks are coming up next without altering
   * the queue's behavior.
   *
   * @param bool True to skip the lease acquisition step.
   */
  public function setSkipLease($skip) {
    $this->skipLease = $skip;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  /**
   * Select only leased tasks, only unleased tasks, or both types of task.
   *
   * By default, queries select only unleased tasks (equivalent to passing
   * `false` to this method). You can pass `true` to select only leased tasks,
   * or `null` to ignore the lease status of tasks.
   *
   * If your result set potentially includes leased tasks, you must disable
   * leasing using @{method:setSkipLease}. These options are intended for use
   * when displaying task status information.
   *
   * @param mixed `true` to select only leased tasks, `false` to select only
   *              unleased tasks (default), or `null` to select both.
   * @return this
   */
  public function withLeasedTasks($leased) {
    $this->leased = $leased;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function execute() {
    if (!$this->limit) {
      throw new Exception(
        pht('You must %s when leasing tasks.', 'setLimit()'));
    }

    if ($this->leased !== false) {
      if (!$this->skipLease) {
        throw new Exception(
          pht(
            'If you potentially select leased tasks using %s, '.
            'you MUST disable lease acquisition by calling %s.',
            'withLeasedTasks()',
            'setSkipLease()'));
      }
    }

    $task_table = new PhabricatorWorkerActiveTask();
    $taskdata_table = new PhabricatorWorkerTaskData();
    $lease_ownership_name = $this->getLeaseOwnershipName();

    $conn_w = $task_table->establishConnection('w');

    // Try to satisfy the request from new, unleased tasks first. If we don't
    // find enough tasks, try tasks with expired leases (i.e., tasks which have
    // previously failed).

    // If we're selecting leased tasks, look for them first.

    $phases = array();
    if ($this->leased !== false) {
      $phases[] = self::PHASE_LEASED;
    }
    if ($this->leased !== true) {
      $phases[] = self::PHASE_UNLEASED;
      $phases[] = self::PHASE_EXPIRED;
    }
    $limit = $this->limit;

    $leased = 0;
    $task_ids = array();
    foreach ($phases as $phase) {
      // NOTE: If we issue `UPDATE ... WHERE ... ORDER BY id ASC`, the query
      // goes very, very slowly. The `ORDER BY` triggers this, although we get
      // the same apparent results without it. Without the ORDER BY, binary
      // read slaves complain that the query isn't repeatable. To avoid both
      // problems, do a SELECT and then an UPDATE.

      $rows = queryfx_all(
        $conn_w,
        'SELECT id, leaseOwner FROM %T %Q %Q %Q',
        $task_table->getTableName(),
        $this->buildCustomWhereClause($conn_w, $phase),
        $this->buildOrderClause($conn_w, $phase),
        $this->buildLimitClause($conn_w, $limit - $leased));

      // NOTE: Sometimes, we'll race with another worker and they'll grab
      // this task before we do. We could reduce how often this happens by
      // selecting more tasks than we need, then shuffling them and trying
      // to lock only the number we're actually after. However, the amount
      // of time workers spend here should be very small relative to their
      // total runtime, so keep it simple for the moment.

      if ($rows) {
        if ($this->skipLease) {
          $leased += count($rows);
          $task_ids += array_fuse(ipull($rows, 'id'));
        } else {
          queryfx(
            $conn_w,
            'UPDATE %T task
              SET leaseOwner = %s, leaseExpires = UNIX_TIMESTAMP() + %d
              %Q',
            $task_table->getTableName(),
            $lease_ownership_name,
            self::getDefaultLeaseDuration(),
            $this->buildUpdateWhereClause($conn_w, $phase, $rows));

          $leased += $conn_w->getAffectedRows();
        }

        if ($leased == $limit) {
          break;
        }
      }
    }

    if (!$leased) {
      return array();
    }

    if ($this->skipLease) {
      $selection_condition = qsprintf(
        $conn_w,
        'task.id IN (%Ld)',
        $task_ids);
    } else {
      $selection_condition = qsprintf(
        $conn_w,
        'task.leaseOwner = %s AND leaseExpires > UNIX_TIMESTAMP()',
        $lease_ownership_name);
    }

    $data = queryfx_all(
      $conn_w,
      'SELECT task.*, taskdata.data _taskData, UNIX_TIMESTAMP() _serverTime
        FROM %T task LEFT JOIN %T taskdata
          ON taskdata.id = task.dataID
        WHERE %Q %Q %Q',
      $task_table->getTableName(),
      $taskdata_table->getTableName(),
      $selection_condition,
      $this->buildOrderClause($conn_w, $phase),
      $this->buildLimitClause($conn_w, $limit));

    $tasks = $task_table->loadAllFromArray($data);
    $tasks = mpull($tasks, null, 'getID');

    foreach ($data as $row) {
      $tasks[$row['id']]->setServerTime($row['_serverTime']);
      if ($row['_taskData']) {
        $task_data = json_decode($row['_taskData'], true);
      } else {
        $task_data = null;
      }
      $tasks[$row['id']]->setData($task_data);
    }

    if ($this->skipLease) {
      // Reorder rows into the original phase order if this is a status query.
      $tasks = array_select_keys($tasks, $task_ids);
    }

    return $tasks;
  }

  protected function buildCustomWhereClause(
    AphrontDatabaseConnection $conn,
    $phase) {

    $where = array();

    switch ($phase) {
      case self::PHASE_LEASED:
        $where[] = qsprintf(
          $conn,
          'leaseOwner IS NOT NULL');
        $where[] = qsprintf(
          $conn,
          'leaseExpires >= UNIX_TIMESTAMP()');
        break;
      case self::PHASE_UNLEASED:
        $where[] = qsprintf(
          $conn,
          'leaseOwner IS NULL');
        break;
      case self::PHASE_EXPIRED:
        $where[] = qsprintf(
          $conn,
          'leaseExpires < UNIX_TIMESTAMP()');
        break;
      default:
        throw new Exception(pht("Unknown phase '%s'!", $phase));
    }

    if ($this->ids !== null) {
      $where[] = qsprintf($conn, 'id IN (%Ld)', $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf($conn, 'objectPHID IN (%Ls)', $this->objectPHIDs);
    }

    return $this->formatWhereClause($conn, $where);
  }

  private function buildUpdateWhereClause(
    AphrontDatabaseConnection $conn,
    $phase,
    array $rows) {

    $where = array();

    // NOTE: This is basically working around the MySQL behavior that
    // `IN (NULL)` doesn't match NULL.

    switch ($phase) {
      case self::PHASE_LEASED:
        throw new Exception(
          pht(
            'Trying to lease tasks selected in the leased phase! This is '.
            'intended to be impossible.'));
      case self::PHASE_UNLEASED:
        $where[] = qsprintf($conn, 'leaseOwner IS NULL');
        $where[] = qsprintf($conn, 'id IN (%Ld)', ipull($rows, 'id'));
        break;
      case self::PHASE_EXPIRED:
        $in = array();
        foreach ($rows as $row) {
          $in[] = qsprintf(
            $conn,
            '(id = %d AND leaseOwner = %s)',
            $row['id'],
            $row['leaseOwner']);
        }
        $where[] = qsprintf($conn, '%LO', $in);
        break;
      default:
        throw new Exception(pht('Unknown phase "%s"!', $phase));
    }

    return $this->formatWhereClause($conn, $where);
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_w, $phase) {
    switch ($phase) {
      case self::PHASE_LEASED:
        // Ideally we'd probably order these by lease acquisition time, but
        // we don't have that handy and this is a good approximation.
        return qsprintf($conn_w, 'ORDER BY priority ASC, id ASC');
      case self::PHASE_UNLEASED:
        // When selecting new tasks, we want to consume them in order of
        // increasing priority (and then FIFO).
        return qsprintf($conn_w, 'ORDER BY priority ASC, id ASC');
      case self::PHASE_EXPIRED:
        // When selecting failed tasks, we want to consume them in roughly
        // FIFO order of their failures, which is not necessarily their original
        // queue order.

        // Particularly, this is important for tasks which use soft failures to
        // indicate that they are waiting on other tasks to complete: we need to
        // push them to the end of the queue after they fail, at least on
        // average, so we don't deadlock retrying the same blocked task over
        // and over again.
        return qsprintf($conn_w, 'ORDER BY leaseExpires ASC');
      default:
        throw new Exception(pht('Unknown phase "%s"!', $phase));
    }
  }

  private function buildLimitClause(AphrontDatabaseConnection $conn_w, $limit) {
    return qsprintf($conn_w, 'LIMIT %d', $limit);
  }

  private function getLeaseOwnershipName() {
    static $sequence = 0;

    // TODO: If the host name is very long, this can overflow the 64-character
    // column, so we pick just the first part of the host name. It might be
    // useful to just use a random hash as the identifier instead and put the
    // pid / time / host (which are somewhat useful diagnostically) elsewhere.
    // Likely, we could store a daemon ID instead and use that to identify
    // when and where code executed. See T6742.

    $host = php_uname('n');
    $host = id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(32)
      ->setTerminator('...')
      ->truncateString($host);

    $parts = array(
      getmypid(),
      time(),
      $host,
      ++$sequence,
    );

    return implode(':', $parts);
  }

}
