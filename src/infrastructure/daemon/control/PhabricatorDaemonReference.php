<?php

final class PhabricatorDaemonReference extends Phobject {

  private $name;
  private $argv;
  private $pid;
  private $start;
  private $pidFile;

  private $daemonLog;

  public static function loadReferencesFromFile($path) {
    $pid_data = Filesystem::readFile($path);

    try {
      $dict = phutil_json_decode($pid_data);
    } catch (PhutilJSONParserException $ex) {
      $dict = array();
    }

    $refs = array();
    $daemons = idx($dict, 'daemons', array());

    $logs = array();

    $daemon_ids = ipull($daemons, 'id');
    if ($daemon_ids) {
      try {
        $logs = id(new PhabricatorDaemonLogQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withDaemonIDs($daemon_ids)
          ->execute();
      } catch (AphrontQueryException $ex) {
        // Ignore any issues here; getting this information only allows us
        // to provide a more complete picture of daemon status, and we want
        // these commands to work if the database is inaccessible.
      }

      $logs = mpull($logs, null, 'getDaemonID');
    }

    // Support PID files that use the old daemon format, where each overseer
    // had exactly one daemon. We can eventually remove this; they will still
    // be stopped by `phd stop --force` even if we don't identify them here.
    if (!$daemons && idx($dict, 'name')) {
      $daemons = array(
        array(
          'config' => array(
            'class' => idx($dict, 'name'),
            'argv' => idx($dict, 'argv', array()),
          ),
        ),
      );
    }

    foreach ($daemons as $daemon) {
      $ref = new PhabricatorDaemonReference();

      // NOTE: This is the overseer PID, not the actual daemon process PID.
      // This is correct for checking status and sending signals (the only
      // things we do with it), but might be confusing. $daemon['pid'] has
      // the daemon PID, and we could expose that if we had some use for it.

      $ref->pid = idx($dict, 'pid');
      $ref->start = idx($dict, 'start');

      $config = idx($daemon, 'config', array());
      $ref->name = idx($config, 'class');
      $ref->argv = idx($config, 'argv', array());

      $log = idx($logs, idx($daemon, 'id'));
      if ($log) {
        $ref->daemonLog = $log;
      }

      $ref->pidFile = $path;
      $refs[] = $ref;
    }

    return $refs;
  }

  public function updateStatus($new_status) {
    if (!$this->daemonLog) {
      return;
    }

    try {
      $this->daemonLog
        ->setStatus($new_status)
        ->save();
    } catch (AphrontQueryException $ex) {
      // Ignore anything that goes wrong here.
    }
  }

  public function getPID() {
    return $this->pid;
  }

  public function getName() {
    return $this->name;
  }

  public function getArgv() {
    return $this->argv;
  }

  public function getEpochStarted() {
    return $this->start;
  }

  public function getPIDFile() {
    return $this->pidFile;
  }

  public function getDaemonLog() {
    return $this->daemonLog;
  }

  public function isRunning() {
    return self::isProcessRunning($this->getPID());
  }

  public static function isProcessRunning($pid) {
    if (!$pid) {
      return false;
    }

    if (function_exists('posix_kill')) {
      // This may fail if we can't signal the process because we are running as
      // a different user (for example, we are 'apache' and the process is some
      // other user's, or we are a normal user and the process is root's), but
      // we can check the error code to figure out if the process exists.
      $is_running = posix_kill($pid, 0);
      if (posix_get_last_error() == 1) {
        // "Operation Not Permitted", indicates that the PID exists. If it
        // doesn't, we'll get an error 3 ("No such process") instead.
        $is_running = true;
      }
    } else {
      // If we don't have the posix extension, just exec.
      list($err) = exec_manual('ps %s', $pid);
      $is_running = ($err == 0);
    }

    return $is_running;
  }

  public function waitForExit($seconds) {
    $start = time();
    while (time() < $start + $seconds) {
      usleep(100000);
      if (!$this->isRunning()) {
        return true;
      }
    }
    return !$this->isRunning();
  }

}
