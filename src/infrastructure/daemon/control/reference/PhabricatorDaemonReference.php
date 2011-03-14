<?php

/*
 * Copyright 2011 Facebook, Inc.
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

  public static function newFromDictionary(array $dict) {
    $ref = new PhabricatorDaemonReference();

    $ref->name  = idx($dict, 'name', 'Unknown');
    $ref->pid   = idx($dict, 'pid');
    $ref->start = idx($dict, 'start');

    return $ref;
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
    $pid = $this->getPID();
    if (!$pid) {
      return false;
    }
    return posix_kill($pid, 0);
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
