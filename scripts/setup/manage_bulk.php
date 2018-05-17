#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage bulk jobs'));
$args->setSynopsis(<<<EOSYNOPSIS
**bulk** __command__ [__options__]
  Manage and debug bulk jobs.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilClassMapQuery())
  ->setAncestorClass('PhabricatorBulkManagementWorkflow')
  ->execute();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
