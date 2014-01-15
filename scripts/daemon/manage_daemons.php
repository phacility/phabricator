#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

PhabricatorDaemonManagementWorkflow::requireExtensions();

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage daemons');
$args->setSynopsis(<<<EOSYNOPSIS
**phd** __command__ [__options__]
    Manage Phabricator daemons.

EOSYNOPSIS
  );
$args->parseStandardArguments();
$workflows = array(
  new PhabricatorDaemonManagementListWorkflow(),
  new PhabricatorDaemonManagementStatusWorkflow(),
  new PhabricatorDaemonManagementStartWorkflow(),
  new PhabricatorDaemonManagementStopWorkflow(),
  new PhabricatorDaemonManagementRestartWorkflow(),
  new PhabricatorDaemonManagementLaunchWorkflow(),
  new PhabricatorDaemonManagementDebugWorkflow(),
  new PhabricatorDaemonManagementLogWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
