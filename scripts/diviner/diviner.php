#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);

$args->setTagline('documentation generator');
$args->setSynopsis(<<<EOHELP
**diviner** __command__ [__options__]
  Generate documentation.
EOHELP
);
$args->parseStandardArguments();

$args->parseWorkflows(
  array(
    new DivinerGenerateWorkflow(),
    new DivinerAtomizeWorkflow(),
    new PhutilHelpArgumentWorkflow(),
  ));
