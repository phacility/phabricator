<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

  public function loadDaemonLog() {
    if (!$this->daemonLog) {
      $this->daemonLog = id(new PhabricatorDaemonLog())->loadOneWhere(
        'daemon = %s AND pid = %d AND dateCreated = %d',
        $this->name,
        $this->pid,
        $this->start);
    }
    return $this->daemonLog;
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
