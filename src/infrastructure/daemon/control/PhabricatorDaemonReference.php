<?php

final class PhabricatorDaemonReference {

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

    foreach ($daemons as $daemon) {
      $ref = new PhabricatorDaemonReference();

      // NOTE: This is the overseer PID, not the actual daemon process PID.
      // This is correct for checking status and sending signals (the only
      // things we do with it), but might be confusing. $daemon['pid'] has
      // the daemon PID, and we could expose that if we had some use for it.

      $ref->pid = idx($dict, 'pid');
      $ref->start = idx($dict, 'start');

      $ref->name = idx($daemon, 'class');
      $ref->argv = idx($daemon, 'argv', array());


      // TODO: We previously identified daemon logs by using a <class, pid,
      // epoch> tuple, but now all daemons under a single overseer will share
      // that identifier. We can uniquely identify daemons by $daemon['id'],
      // but that isn't currently written into the daemon logs. We should
      // start writing it, then load the logs here. This would give us a
      // slightly greater ability to keep the web UI in sync when daemons
      // get killed forcefully and clean up `phd status` a bit.

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
