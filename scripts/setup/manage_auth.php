#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage authentication');
$args->setSynopsis(<<<EOSYNOPSIS
**auth** __command__ [__options__]
    Manage Phabricator authentication configuration.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorAuthManagementRecoverWorkflow(),
  new PhabricatorAuthManagementRefreshWorkflow(),
  new PhabricatorAuthManagementLDAPWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
