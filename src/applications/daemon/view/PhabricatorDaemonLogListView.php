<?php

final class PhabricatorDaemonLogListView extends AphrontView {

  private $daemonLogs;

  public function setDaemonLogs(array $daemon_logs) {
    assert_instances_of($daemon_logs, 'PhabricatorDaemonLog');
    $this->daemonLogs = $daemon_logs;
    return $this;
  }

  public function render() {
    $viewer = $this->getViewer();

    $rows = array();

    $list = new PHUIObjectItemListView();
    $list->setFlush(true);
    foreach ($this->daemonLogs as $log) {
      $id = $log->getID();
      $epoch = $log->getDateCreated();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Daemon %s', $id))
        ->setHeader($log->getDaemon())
        ->setHref("/daemon/log/{$id}/")
        ->addIcon('none', phabricator_datetime($epoch, $viewer));

      $status = $log->getStatus();
      switch ($status) {
        case PhabricatorDaemonLog::STATUS_RUNNING:
          $item->setStatusIcon('fa-rocket green');
          $item->addAttribute(pht('This daemon is running.'));
          break;
        case PhabricatorDaemonLog::STATUS_DEAD:
          $item->setStatusIcon('fa-warning red');
          $item->addAttribute(
            pht(
              'This daemon is lost or exited uncleanly, and is presumed '.
              'dead.'));
          $item->addIcon('fa-times grey', pht('Dead'));
          break;
        case PhabricatorDaemonLog::STATUS_EXITING:
          $item->addAttribute(pht('This daemon is exiting.'));
          $item->addIcon('fa-check', pht('Exiting'));
          break;
        case PhabricatorDaemonLog::STATUS_EXITED:
          $item->setDisabled(true);
          $item->addAttribute(pht('This daemon exited cleanly.'));
          $item->addIcon('fa-check grey', pht('Exited'));
          break;
        case PhabricatorDaemonLog::STATUS_WAIT:
          $item->setStatusIcon('fa-clock-o blue');
          $item->addAttribute(
            pht(
              'This daemon encountered an error recently and is waiting a '.
              'moment to restart.'));
          $item->addIcon('fa-clock-o grey', pht('Waiting'));
          break;
        case PhabricatorDaemonLog::STATUS_UNKNOWN:
        default:
          $item->setStatusIcon('fa-warning orange');
          $item->addAttribute(
            pht(
              'This daemon has not reported its status recently. It may '.
              'have exited uncleanly.'));
          $item->addIcon('fa-warning', pht('Unknown'));
          break;
      }

      $list->addItem($item);
    }

    return $list;
  }

}
