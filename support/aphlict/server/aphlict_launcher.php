#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root.'/scripts/__init_script__.php';

PhabricatorAphlictManagementWorkflow::requireExtensions();

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage Aphlict notification server'));
$args->setSynopsis(<<<EOSYNOPSIS
**aphlict** __command__ [__options__]
    Manage the Aphlict server.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('PhabricatorAphlictManagementWorkflow')
  ->loadObjects();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
