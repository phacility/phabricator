#!/usr/bin/env php
<?php

require_once dirname(__FILE__).'/../__init_script__.php';

$synopsis = <<<EOT
**save_lint.php**
    Discover lint problems and save them to database so that they can
    be displayed in Diffusion.

EOT;

$args = id(new PhutilArgumentParser($argv))
  ->setTagline('save lint errors to database')
  ->setSynopsis($synopsis)
  ->parseStandardArguments()
  ->parse(array(
    array(
      'name' => 'all',
      'help' =>
        "Discover problems in the whole repository instead of just changes ".
        "since the last run.",
    ),
    array(
      'name' => 'arc',
      'param' => 'path',
      'default' => 'arc',
      'help' => "Path to Arcanist executable.",
    ),
    array(
      'name' => 'severity',
      'param' => 'string',
      'default' => ArcanistLintSeverity::SEVERITY_ADVICE,
      'help' => "Minimum severity, one of ArcanistLintSeverity constants.",
    ),
    array(
      'name' => 'chunk-size',
      'param' => 'number',
      'default' => 256,
      'help' => "Number of paths passed to `arc` at once.",
    ),
    array(
      'name' => 'blame',
      'help' => "Assign lint errors to authors who last modified the line.",
    ),
  ));

echo "Saving lint errors to database...\n";

$count = id(new DiffusionLintSaveRunner())
  ->setAll($args->getArg('all', false))
  ->setArc($args->getArg('arc'))
  ->setSeverity($args->getArg('severity'))
  ->setChunkSize($args->getArg('chunk-size'))
  ->setNeedsBlame($args->getArg('blame'))
  ->run('.');

echo "\nProcessed {$count} files.\n";
