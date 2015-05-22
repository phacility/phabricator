#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage drydock software resources'));
$args->setSynopsis(<<<EOSYNOPSIS
**drydock** __commmand__ [__options__]
    Manage Drydock stuff. NEW AND EXPERIMENTAL.

EOSYNOPSIS
);
$args->parseStandardArguments();

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('DrydockManagementWorkflow')
  ->loadObjects();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
