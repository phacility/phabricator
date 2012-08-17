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

/**
 * Responds to IRC messages. You plug a bunch of these into a
 * @{class:PhabricatorIRCBot} to give it special behavior.
 *
 * @group irc
 */
abstract class PhabricatorIRCHandler {

  private $bot;

  final public function __construct(PhabricatorIRCBot $irc_bot) {
    $this->bot = $irc_bot;
  }

  final protected function write($command, $message) {
    $this->bot->writeCommand($command, $message);
    return $this;
  }

  final protected function getConduit() {
    return $this->bot->getConduit();
  }

  final protected function getConfig($key, $default = null) {
    return $this->bot->getConfig($key, $default);
  }

  final protected function getURI($path) {
    $base_uri = new PhutilURI($this->bot->getConfig('conduit.uri'));
    $base_uri->setPath($path);
    return (string)$base_uri;
  }

  final protected function isChannelName($name) {
    return (strncmp($name, '#', 1) === 0);
  }

  abstract public function receiveMessage(PhabricatorIRCMessage $message);

  public function runBackgroundTasks() {
    return;
  }

}
