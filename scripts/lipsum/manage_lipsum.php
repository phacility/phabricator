#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage lipsum');
$args->setSynopsis(<<<EOSYNOPSIS
**lipsum** __command__ [__options__]
    Manage Phabricator Test Data Generator.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorLipsumGenerateWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
