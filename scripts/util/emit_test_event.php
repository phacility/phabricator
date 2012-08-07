#!/usr/bin/env php
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

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('emit a test event');
$args->setSynopsis(<<<EOHELP
**emit_test_event.php** [--listen listener] ...
  Emit a test event after installing any specified __listener__s.
EOHELP
);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'    => 'listen',
      'param'   => 'listener',
      'repeat'  => true,
    ),
  ));

$console = PhutilConsole::getConsole();
foreach ($args->getArg('listen') as $listener) {
  $console->writeOut("Installing '%s'...\n", $listener);
  newv($listener, array())->register();
}


$console->writeOut("Emitting event...\n");

PhutilEventEngine::dispatchEvent(
  new PhabricatorEvent(
    PhabricatorEventType::TYPE_TEST_DIDRUNTEST,
    array(
      'time' => time(),
    )));

$console->writeOut("Done.\n");
exit(0);
