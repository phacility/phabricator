#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage files');
$args->setSynopsis(<<<EOSYNOPSIS
**files** __command__ [__options__]
    Manage Phabricator file storage.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhabricatorFilesManagementEnginesWorkflow(),
  new PhabricatorFilesManagementMigrateWorkflow(),
  new PhutilHelpArgumentWorkflow(),
  new PhabricatorFilesManagementMetadataWorkflow(),
);

$args->parseWorkflows($workflows);
