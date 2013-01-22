#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage configuration');
$args->setSynopsis(<<<EOSYNOPSIS
**config** __command__ [__options__]
    Manage Phabricator configuration.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorConfigManagementListWorkflow(),
  new PhabricatorConfigManagementSetWorkflow(),
  new PhabricatorConfigManagementGetWorkflow(),
  new PhabricatorConfigManagementDeleteWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
