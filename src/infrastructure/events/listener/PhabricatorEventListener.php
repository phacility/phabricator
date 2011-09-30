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

abstract class PhabricatorEventListener {

  private $listenerID;
  private static $nextListenerID = 1;

  final public function __construct() {
    // <empty>
  }

  abstract public function register();
  abstract public function handleEvent(PhabricatorEvent $event);

  final public function listen($type) {
    $engine = PhabricatorEventEngine::getInstance();
    $engine->addListener($this, $type);
  }


  /**
   * Return a scalar ID unique to this listener. This is used to deduplicate
   * listeners which match events on multiple rules, so they are invoked only
   * once.
   *
   * @return int A scalar unique to this object instance.
   */
  final public function getListenerID() {
    if (!$this->listenerID) {
      $this->listenerID = self::$nextListenerID;
      self::$nextListenerID++;
    }
    return $this->listenerID;
  }


}
