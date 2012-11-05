#!/usr/bin/env php
<?php

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
