<?php

final class PhabricatorDaemonLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  const STATUS_ALL = 'status-all';
  const STATUS_ALIVE = 'status-alive';
  const STATUS_RUNNING = 'status-running';

  private $ids;
  private $notIDs;
  private $status = self::STATUS_ALL;
  private $daemonClasses;
  private $allowStatusWrites;
  private $daemonIDs;

  public static function getTimeUntilUnknown() {
    return 3 * PhutilDaemonHandle::getHeartbeatEventFrequency();
  }

  public static function getTimeUntilDead() {
    return 30 * PhutilDaemonHandle::getHeartbeatEventFrequency();
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withoutIDs(array $ids) {
    $this->notIDs = $ids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withDaemonClasses(array $classes) {
    $this->daemonClasses = $classes;
    return $this;
  }

  public function setAllowStatusWrites($allow) {
    $this->allowStatusWrites = $allow;
    return $this;
  }

  public function withDaemonIDs(array $daemon_ids) {
    $this->daemonIDs = $daemon_ids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorDaemonLog();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $daemons) {
    $unknown_delay = PhabricatorDaemonLogQuery::getTimeUntilUnknown();
    $dead_delay = PhabricatorDaemonLogQuery::getTimeUntilDead();

    $status_running = PhabricatorDaemonLog::STATUS_RUNNING;
    $status_unknown = PhabricatorDaemonLog::STATUS_UNKNOWN;
    $status_wait = PhabricatorDaemonLog::STATUS_WAIT;
    $status_exiting = PhabricatorDaemonLog::STATUS_EXITING;
    $status_exited = PhabricatorDaemonLog::STATUS_EXITED;
    $status_dead = PhabricatorDaemonLog::STATUS_DEAD;

    $filter = array_fuse($this->getStatusConstants());

    foreach ($daemons as $key => $daemon) {
      $status = $daemon->getStatus();
      $seen = $daemon->getDateModified();

      $is_running = ($status == $status_running) ||
                    ($status == $status_wait) ||
                    ($status == $status_exiting);

      // If we haven't seen the daemon recently, downgrade its status to
      // unknown.
      $unknown_time = ($seen + $unknown_delay);
      if ($is_running && ($unknown_time < time())) {
        $status = $status_unknown;
      }

      // If the daemon hasn't been seen in quite a while, assume it is dead.
      $dead_time = ($seen + $dead_delay);
      if (($status == $status_unknown) && ($dead_time < time())) {
        $status = $status_dead;
      }

      // If we changed the daemon's status, adjust it.
      if ($status != $daemon->getStatus()) {
        $daemon->setStatus($status);

        // ...and write it, if we're in a context where that's reasonable.
        if ($this->allowStatusWrites) {
          $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
            $daemon->save();
          unset($guard);
        }
      }

      // If the daemon no longer matches the filter, get rid of it.
      if ($filter) {
        if (empty($filter[$daemon->getStatus()])) {
          unset($daemons[$key]);
        }
      }
    }

    return $daemons;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->notIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id NOT IN (%Ld)',
        $this->notIDs);
    }

    if ($this->getStatusConstants()) {
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ls)',
        $this->getStatusConstants());
    }

    if ($this->daemonClasses !== null) {
      $where[] = qsprintf(
        $conn_r,
        'daemon IN (%Ls)',
        $this->daemonClasses);
    }

    if ($this->daemonIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'daemonID IN (%Ls)',
        $this->daemonIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);
    return $this->formatWhereClause($where);
  }

  private function getStatusConstants() {
    $status = $this->status;
    switch ($status) {
      case self::STATUS_ALL:
        return array();
      case self::STATUS_RUNNING:
        return array(
          PhabricatorDaemonLog::STATUS_RUNNING,
        );
      case self::STATUS_ALIVE:
        return array(
          PhabricatorDaemonLog::STATUS_UNKNOWN,
          PhabricatorDaemonLog::STATUS_RUNNING,
          PhabricatorDaemonLog::STATUS_WAIT,
          PhabricatorDaemonLog::STATUS_EXITING,
        );
      default:
        throw new Exception(pht('Unknown status "%s"!', $status));
    }
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDaemonsApplication';
  }

}
