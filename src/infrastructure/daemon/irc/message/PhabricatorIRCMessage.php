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

final class PhabricatorIRCMessage {

  private $sender;
  private $command;
  private $data;

  public function __construct($sender, $command, $data) {
    $this->sender = $sender;
    $this->command = $command;
    $this->data = $data;
  }

  public function getRawSender() {
    return $this->sender;
  }

  public function getRawData() {
    return $this->data;
  }

  public function getCommand() {
    return $this->command;
  }

  public function getReplyTo() {
    switch ($this->getCommand()) {
      case 'PRIVMSG':
        $target = $this->getTarget();
        if ($target[0] == '#') {
          return $target;
        }

        $matches = null;
        if (preg_match('/^:([^!]+)!/', $this->sender, $matches)) {
          return $matches[1];
        }
        break;
    }
    return null;
  }

  public function getTarget() {
    switch ($this->getCommand()) {
      case 'PRIVMSG':
        $matches = null;
        $raw = $this->getRawData();
        if (preg_match('/^(\S+)\s/', $raw, $matches)) {
          return $matches[1];
        }
       break;
    }
    return null;
  }

  public function getMessageText() {
    switch ($this->getCommand()) {
      case 'PRIVMSG':
        $matches = null;
        $raw = $this->getRawData();
        if (preg_match('/^\S+\s+:?(.*)$/', $raw, $matches)) {
          return rtrim($matches[1], "\r\n");
        }
        break;
    }
    return null;
  }

}
