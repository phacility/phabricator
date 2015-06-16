#!/usr/bin/env php
<?php

require_once dirname(__FILE__).'/../__init_script__.php';

$synopsis = <<<EOT
**save_lint.php**
    Discover lint problems and save them to database so that they can
    be displayed in Diffusion.

EOT;

$args = id(new PhutilArgumentParser($argv))
  ->setTagline(pht('save lint errors to database'))
  ->setSynopsis($synopsis)
  ->parseStandardArguments()
  ->parse(array(
    array(
      'name' => 'all',
      'help' => pht(
        'Discover problems in the whole repository instead of just changes '.
        'since the last run.'),
    ),
    array(
      'name' => 'arc',
      'param' => 'path',
      'default' => 'arc',
      'help' => pht('Path to Arcanist executable.'),
    ),
    array(
      'name' => 'severity',
      'param' => 'string',
      'default' => ArcanistLintSeverity::SEVERITY_ADVICE,
      'help' => pht(
        'Minimum severity, one of %s constants.',
        'ArcanistLintSeverity'),
    ),
    array(
      'name' => 'chunk-size',
      'param' => 'number',
      'default' => 256,
      'help' => pht('Number of paths passed to `%s` at once.', 'arc'),
    ),
    array(
      'name' => 'blame',
      'help' => pht(
        'Assign lint errors to authors who last modified the line.'),
    ),
  ));

echo pht('Saving lint errors to database...')."\n";

$count = id(new DiffusionLintSaveRunner())
  ->setAll($args->getArg('all', false))
  ->setArc($args->getArg('arc'))
  ->setSeverity($args->getArg('severity'))
  ->setChunkSize($args->getArg('chunk-size'))
  ->setNeedsBlame($args->getArg('blame'))
  ->run('.');

echo "\n".pht('Processed %d files.', $count)."\n";
