<?php

final class PhabricatorDaemonLogListView extends AphrontView {

  private $daemonLogs;

  public function setDaemonLogs(array $daemon_logs) {
    assert_instances_of($daemon_logs, 'PhabricatorDaemonLog');
    $this->daemonLogs = $daemon_logs;
    return $this;
  }

  public function render() {
    $rows = array();

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    $list = id(new PhabricatorObjectItemListView());
    foreach ($this->daemonLogs as $log) {

      // TODO: VVV Move this stuff to a Query class. VVV

      $expect_heartbeat = PhabricatorDaemonLogQuery::getTimeUntilUnknown();
      $assume_dead = PhabricatorDaemonLogQuery::getTimeUntilDead();

      $status_running = PhabricatorDaemonLog::STATUS_RUNNING;
      $status_unknown = PhabricatorDaemonLog::STATUS_UNKNOWN;
      $status_wait = PhabricatorDaemonLog::STATUS_WAIT;
      $status_exited = PhabricatorDaemonLog::STATUS_EXITED;
      $status_dead = PhabricatorDaemonLog::STATUS_DEAD;

      $status = $log->getStatus();
      $heartbeat_timeout = $log->getDateModified() + $expect_heartbeat;
      if ($status == $status_running && $heartbeat_timeout < time()) {
        $status = $status_unknown;
      }

      if ($status == $status_unknown && $assume_dead < time()) {
        $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
        $log->setStatus($status_dead)->save();
        unset($guard);
      }

      if ($status != $status_running &&
          $log->getDateModified() + (3 * 86400) < time()) {
        // Don't show rows that haven't been running for more than
        // three days.  We should probably prune these out of the
        // DB similar to the code above, but we don't need to be
        // conservative and do it only on the same host

        // TODO: This should not apply to the "all daemons" view!
        continue;
      }

      // TODO: ^^^^ ALL THAT STUFF ^^^

      $id = $log->getID();
      $epoch = $log->getDateCreated();

      $item = id(new PhabricatorObjectItemView())
        ->setObjectName(pht("Daemon %s", $id))
        ->setHeader($log->getDaemon())
        ->setHref("/daemon/log/{$id}/")
        ->addIcon('none', phabricator_datetime($epoch, $this->user));

      switch ($status) {
        case PhabricatorDaemonLog::STATUS_RUNNING:
          $item->setBarColor('green');
          $item->addAttribute(pht('This daemon is running.'));
          break;
        case PhabricatorDaemonLog::STATUS_DEAD:
          $item->setBarColor('red');
          $item->addAttribute(
            pht(
              'This daemon is lost or exited uncleanly, and is presumed '.
              'dead.'));
          $item->addIcon('delete', pht('Dead'));
          break;
        case PhabricatorDaemonLog::STATUS_EXITED:
          $item->setDisabled(true);
          $item->addAttribute(pht('This daemon exited cleanly.'));
          $item->addIcon('enable-grey', pht('Exited'));
          break;
        case PhabricatorDaemonLog::STATUS_WAIT:
          $item->setBarColor('blue');
          $item->addAttribute(
            pht(
              'This daemon encountered an error recently and is waiting a '.
              'moment to restart.'));
          $item->addIcon('perflab-grey', pht('Waiting'));
          break;
        case PhabricatorDaemonLog::STATUS_UNKNOWN:
        default:
          $item->setBarColor('orange');
          $item->addAttribute(
            pht(
              'This daemon has not reported its status recently. It may '.
              'have exited uncleanly.'));
          $item->addIcon('warning', pht('Unknown'));
          break;
      }

      $list->addItem($item);
    }

    return $list;
  }

}
