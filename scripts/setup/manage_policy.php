#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage policies'));
$args->setSynopsis(<<<EOSYNOPSIS
**policy** __command__ [__options__]
    Administrative tool for reviewing and editing policies.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('PhabricatorPolicyManagementWorkflow')
  ->loadObjects();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
