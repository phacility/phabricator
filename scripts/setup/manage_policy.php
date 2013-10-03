#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage policies');
$args->setSynopsis(<<<EOSYNOPSIS
**policy** __command__ [__options__]
    Administrative tool for reviewing and editing policies.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorPolicyManagementShowWorkflow(),
  new PhabricatorPolicyManagementUnlockWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
