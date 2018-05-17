#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/init/init-script.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage Conduit'));
$args->setSynopsis(<<<EOSYNOPSIS
**conduit** __command__ [__options__]
    Manage Conduit.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilClassMapQuery())
  ->setAncestorClass('PhabricatorConduitManagementWorkflow')
  ->execute();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
