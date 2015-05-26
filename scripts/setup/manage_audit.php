#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage audits'));
$args->setSynopsis(<<<EOSYNOPSIS
**audit** __command__ [__options__]
    Manage Phabricator audits.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('PhabricatorAuditManagementWorkflow')
  ->loadObjects();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
