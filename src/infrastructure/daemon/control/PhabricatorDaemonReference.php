<?php

final class PhabricatorDaemonReference {

  private $name;
  private $pid;
  private $start;
  private $pidFile;

  private $daemonLog;

  public static function newFromDictionary(array $dict) {
    $ref = new PhabricatorDaemonReference();

    $ref->name  = idx($dict, 'name', 'Unknown');
    $ref->pid   = idx($dict, 'pid');
    $ref->start = idx($dict, 'start');

    return $ref;
  }

  public function updateStatus($new_status) {
    try {
      if (!$this->daemonLog) {
        $this->daemonLog = id(new PhabricatorDaemonLog())->loadOneWhere(
          'daemon = %s AND pid = %d AND dateCreated = %d',
          $this->name,
          $this->pid,
          $this->start);
      }

      if ($this->daemonLog) {
        $this->daemonLog
          ->setStatus($new_status)
          ->save();
      }
    } catch (AphrontQueryException $ex) {
      // Ignore anything that goes wrong here. We anticipate at least two
      // specific failure modes:
      //
      //   - Upgrade scripts which run `git pull`, then `phd stop`, then
      //     `bin/storage upgrade` will fail when trying to update the `status`
      //     column, as it does not exist yet.
      //   - Daemons running on machines which do not have access to MySQL
      //     (like an IRC bot) will not be able to load or save the log.
      //
      //
    }
  }

  public function getPID() {
    return $this->pid;
  }

  public function getName() {
    return $this->name;
  }

  public function getEpochStarted() {
    return $this->start;
  }

  public function setPIDFile($pid_file) {
    $this->pidFile = $pid_file;
    return $this;
  }

  public function getPIDFile() {
    return $this->pidFile;
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
