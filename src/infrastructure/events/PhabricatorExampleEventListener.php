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
 * Example event listener. For details about installing Phabricator event hooks,
 * refer to @{article:Events User Guide: Installing Event Listeners}.
 *
 * @group events
 */
final class PhabricatorExampleEventListener extends PhutilEventListener {

  public function register() {
    // When your listener is installed, its register() method will be called.
    // You should listen() to any events you are interested in here.

    $this->listen(PhabricatorEventType::TYPE_TEST_DIDRUNTEST);
  }

  public function handleEvent(PhutilEvent $event) {
    // When an event you have called listen() for in your register() method
    // occurs, this method will be invoked. You should respond to the event.

    // In this case, we just echo a message out so the event test script will
    // do something visible.

    $console = PhutilConsole::getConsole();
    $console->writeOut(
      "PhabricatorExampleEventListener got test event at %d\n",
      $event->getValue('time'));
  }

}





