#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage repositories');
$args->setSynopsis(<<<EOSYNOPSIS
**repository** __command__ [__options__]
    Manage and debug Phabricator repository configuration, tracking,
    discovery and import.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorRepositoryManagementPullWorkflow(),
  new PhabricatorRepositoryManagementDiscoverWorkflow(),
  new PhabricatorRepositoryManagementListWorkflow(),
  new PhabricatorRepositoryManagementDeleteWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
