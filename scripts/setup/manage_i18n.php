#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('manage internationalization'));
$args->setSynopsis(<<<EOSYNOPSIS
**i18n** __command__ [__options__]
    Manage translations and internationalization.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = id(new PhutilSymbolLoader())
  ->setAncestorClass('PhabricatorInternationalizationManagementWorkflow')
  ->loadObjects();
$workflows[] = new PhutilHelpArgumentWorkflow();
$args->parseWorkflows($workflows);
