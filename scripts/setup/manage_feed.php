#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage feed');
$args->setSynopsis(<<<EOSYNOPSIS
**feed** __command__ [__options__]
    Test and debug feed events.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorFeedManagementRepublishWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
