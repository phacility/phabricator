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
    $daemons = $this->daemonLogs;

    foreach ($daemons as $daemon) {
      $id = $daemon->getID();
      $host = $daemon->getHost();
      $pid = $daemon->getPID();
      $name = phutil_tag(
        'a',
        array(
          'href' => "/daemon/log/{$id}/",
        ),
        $daemon->getDaemon());

      $status = $daemon->getStatus();
      switch ($status) {
        case PhabricatorDaemonLog::STATUS_RUNNING:
          $status_icon = 'fa-rocket green';
          $status_label = pht('Running');
          $status_tip = pht('This daemon is running.');
          break;
        case PhabricatorDaemonLog::STATUS_DEAD:
          $status_icon = 'fa-warning red';
          $status_label = pht('Dead');
          $status_tip = pht(
            'This daemon has been lost or exited uncleanly, and is '.
            'presumed dead.');
          break;
        case PhabricatorDaemonLog::STATUS_EXITING:
          $status_icon = 'fa-check';
          $status_label = pht('Shutting Down');
          $status_tip = pht('This daemon is shutting down.');
          break;
        case PhabricatorDaemonLog::STATUS_EXITED:
          $status_icon = 'fa-check grey';
          $status_label = pht('Exited');
          $status_tip = pht('This daemon exited cleanly.');
          break;
        case PhabricatorDaemonLog::STATUS_WAIT:
          $status_icon = 'fa-clock-o blue';
          $status_label = pht('Waiting');
          $status_tip = pht(
            'This daemon encountered an error recently and is waiting a '.
            'moment to restart.');
          break;
        case PhabricatorDaemonLog::STATUS_UNKNOWN:
        default:
          $status_icon = 'fa-warning orange';
          $status_label = pht('Unknown');
          $status_tip = pht(
            'This daemon has not reported its status recently. It may '.
            'have exited uncleanly.');
          break;
      }

      $status = phutil_tag(
        'span',
        array(
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $status_tip,
          ),
        ),
        array(
          id(new PHUIIconView())->setIcon($status_icon),
          ' ',
          $status_label,
        ));

      $launched = phabricator_datetime($daemon->getDateCreated(), $viewer);

      $rows[] = array(
        $id,
        $host,
        $pid,
        $name,
        $status,
        $launched,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Host'),
          pht('PPID'),
          pht('Daemon'),
          pht('Status'),
          pht('Launched'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          'pri',
          'wide',
          'right date',
        ));

    return $table;
  }

}
