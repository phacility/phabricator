#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/init/init-script.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage locks'));
$args->setSynopsis(<<<EOSYNOPSIS
**lock** __command__ [__options__]
  Manage locks.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilClassMapQuery())
  ->setAncestorClass('PhabricatorLockManagementWorkflow')
  ->execute();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
