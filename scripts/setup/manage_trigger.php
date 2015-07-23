#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage triggers'));
$args->setSynopsis(<<<EOSYNOPSIS
**trigger** __command__ [__options__]
    Manage event triggers.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('PhabricatorWorkerTriggerManagementWorkflow')
  ->loadObjects();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
