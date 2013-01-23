<?php

/**
 * Select and lease tasks from the worker task queue.
 *
 * @group worker
 */
final class PhabricatorWorkerLeaseQuery extends PhabricatorQuery {

  const PHASE_UNLEASED = 'unleased';
  const PHASE_EXPIRED  = 'expired';

  const DEFAULT_LEASE_DURATION = 60; // Seconds

  private $ids;
  private $limit;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function execute() {
    if (!$this->limit) {
      throw new Exception("You must setLimit() when leasing tasks.");
    }

    $task_table = new PhabricatorWorkerActiveTask();
    $taskdata_table = new PhabricatorWorkerTaskData();
    $lease_ownership_name = $this->getLeaseOwnershipName();

    $conn_w = $task_table->establishConnection('w');

    // Try to satisfy the request from new, unleased tasks first. If we don't
    // find enough tasks, try tasks with expired leases (i.e., tasks which have
    // previously failed).

    $phases = array(
      self::PHASE_UNLEASED,
      self::PHASE_EXPIRED,
    );
    $limit = $this->limit;

    $leased = 0;
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
        $this->buildWhereClause($conn_w, $phase),
        $this->buildOrderClause($conn_w),
        $this->buildLimitClause($conn_w, $limit - $leased));

      // NOTE: Sometimes, we'll race with another worker and they'll grab
      // this task before we do. We could reduce how often this happens by
      // selecting more tasks than we need, then shuffling them and trying
      // to lock only the number we're actually after. However, the amount
      // of time workers spend here should be very small relative to their
      // total runtime, so keep it simple for the moment.

      if ($rows) {
        queryfx(
          $conn_w,
          'UPDATE %T task
            SET leaseOwner = %s, leaseExpires = UNIX_TIMESTAMP() + %d
            %Q',
          $task_table->getTableName(),
          $lease_ownership_name,
          self::DEFAULT_LEASE_DURATION,
          $this->buildUpdateWhereClause($conn_w, $phase, $rows));

        $leased += $conn_w->getAffectedRows();
        if ($leased == $limit) {
          break;
        }
      }
    }

    if (!$leased) {
      return array();
    }

    $data = queryfx_all(
      $conn_w,
      'SELECT task.*, taskdata.data _taskData, UNIX_TIMESTAMP() _serverTime
        FROM %T task LEFT JOIN %T taskdata
          ON taskdata.id = task.dataID
        WHERE leaseOwner = %s AND leaseExpires > UNIX_TIMESTAMP()
        %Q %Q',
      $task_table->getTableName(),
      $taskdata_table->getTableName(),
      $lease_ownership_name,
      $this->buildOrderClause($conn_w),
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

    return $tasks;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_w, $phase) {

    $where = array();

    switch ($phase) {
      case self::PHASE_UNLEASED:
        $where[] = 'leaseOwner IS NULL';
        break;
      case self::PHASE_EXPIRED:
        $where[] = 'leaseExpires < UNIX_TIMESTAMP()';
        break;
      default:
        throw new Exception("Unknown phase '{$phase}'!");
    }

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_w,
        'task.id IN (%Ld)',
        $this->ids);
    }

    return $this->formatWhereClause($where);
  }

  private function buildUpdateWhereClause(
    AphrontDatabaseConnection $conn_w,
    $phase,
    array $rows) {

    $where = array();

    // NOTE: This is basically working around the MySQL behavior that
    // `IN (NULL)` doesn't match NULL.

    switch ($phase) {
      case self::PHASE_UNLEASED:
        $where[] = qsprintf(
          $conn_w,
          'leaseOwner IS NULL');
        $where[] = qsprintf(
          $conn_w,
          'id IN (%Ld)',
          ipull($rows, 'id'));
        break;
      case self::PHASE_EXPIRED:
        $in = array();
        foreach ($rows as $row) {
          $in[] = qsprintf(
            $conn_w,
            '(%d, %s)',
            $row['id'],
            $row['leaseOwner']);
        }
        $where[] = qsprintf(
          $conn_w,
          '(id, leaseOwner) IN (%Q)',
          '('.implode(', ', $in).')');
        break;
      default:
        throw new Exception("Unknown phase '{$phase}'!");
    }

    return $this->formatWhereClause($where);

  }

  private function buildOrderClause(AphrontDatabaseConnection $conn_w) {
    return qsprintf($conn_w, 'ORDER BY id ASC');
  }

  private function buildLimitClause(AphrontDatabaseConnection $conn_w, $limit) {
    return qsprintf($conn_w, 'LIMIT %d', $limit);
  }

  private function getLeaseOwnershipName() {
    static $sequence = 0;

    $parts = array(
      getmypid(),
      time(),
      php_uname('n'),
      ++$sequence,
    );

    return implode(':', $parts);
  }

}
