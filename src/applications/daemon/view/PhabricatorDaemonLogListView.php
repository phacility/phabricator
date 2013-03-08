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

    foreach ($this->daemonLogs as $log) {
      $epoch = $log->getDateCreated();

      $status = $log->getStatus();
      if ($log->getHost() == php_uname('n') &&
          $status != PhabricatorDaemonLog::STATUS_EXITED &&
          $status != PhabricatorDaemonLog::STATUS_DEAD) {

        $pid = $log->getPID();
        $is_running = PhabricatorDaemonReference::isProcessRunning($pid);
        if (!$is_running) {
          $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
          $log->setStatus(PhabricatorDaemonLog::STATUS_DEAD);
          $log->save();
          unset($guard);
          $status = PhabricatorDaemonLog::STATUS_DEAD;
        }
      }

      $heartbeat_timeout =
        $log->getDateModified() + 3 * PhutilDaemonOverseer::HEARTBEAT_WAIT;
      if ($status == PhabricatorDaemonLog::STATUS_RUNNING &&
          $heartbeat_timeout < time()) {
        $status = PhabricatorDaemonLog::STATUS_UNKNOWN;
      }

      switch ($status) {
        case PhabricatorDaemonLog::STATUS_RUNNING:
          $style = 'color: #00cc00';
          $title = 'Running';
          $symbol = "\xE2\x80\xA2";
          break;
        case PhabricatorDaemonLog::STATUS_DEAD:
          $style = 'color: #cc0000';
          $title = 'Died';
          $symbol = "\xE2\x80\xA2";
          break;
        case PhabricatorDaemonLog::STATUS_EXITED:
          $style = 'color: #000000';
          $title = 'Exited';
          $symbol = "\xE2\x80\xA2";
          break;
        case PhabricatorDaemonLog::STATUS_UNKNOWN:
        default: // fallthrough
          $style = 'color: #888888';
          $title = 'Unknown';
          $symbol = '?';
      }

      if ($status != PhabricatorDaemonLog::STATUS_RUNNING &&
          $log->getDateModified() + (3 * 86400) < time()) {
        // Don't show rows that haven't been running for more than
        // three days.  We should probably prune these out of the
        // DB similar to the code above, but we don't need to be
        // conservative and do it only on the same host
        continue;
      }

      $running = phutil_tag(
        'span',
        array(
          'style' => $style,
          'title' => $title,
        ),
        $symbol);

      $rows[] = array(
        $running,
        $log->getDaemon(),
        $log->getHost(),
        $log->getPID(),
        phabricator_date($epoch, $this->user),
        phabricator_time($epoch, $this->user),
        phutil_tag(
          'a',
          array(
            'href' => '/daemon/log/'.$log->getID().'/',
            'class' => 'button small grey',
          ),
          'View Log'),
      );
    }

    $daemon_table = new AphrontTableView($rows);
    $daemon_table->setHeaders(
      array(
        '',
        'Daemon',
        'Host',
        'PID',
        'Date',
        'Time',
        'View',
      ));
    $daemon_table->setColumnClasses(
      array(
        '',
        'wide wrap',
        '',
        '',
        '',
        'right',
        'action',
      ));

    return $daemon_table->render();
  }

}
