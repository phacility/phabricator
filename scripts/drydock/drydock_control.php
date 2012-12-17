#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage drydock software resources');
$args->setSynopsis(<<<EOSYNOPSIS
**drydock** __commmand__ [__options__]
    Manage Drydock stuff. NEW AND EXPERIMENTAL.

EOSYNOPSIS
);
$args->parseStandardArguments();

$workflows = array(
  new DrydockManagementWaitForLeaseWorkflow(),
  new DrydockManagementLeaseWorkflow(),
  new DrydockManagementCloseWorkflow(),
  new DrydockManagementReleaseWorkflow(),
  new PhutilHelpArgumentWorkflow(),
);

$args->parseWorkflows($workflows);
