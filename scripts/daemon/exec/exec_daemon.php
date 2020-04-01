#!/usr/bin/env php
<?php

if (function_exists('pcntl_async_signals')) {
  pcntl_async_signals(true);
} else {
  declare(ticks = 1);
}

require_once dirname(__FILE__).'/../../__init_script__.php';

if (!posix_isatty(STDOUT)) {
  $sid = posix_setsid();
  if ($sid <= 0) {
    throw new Exception(pht('Failed to create new process session!'));
  }
}

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('daemon executor'));
$args->setSynopsis(<<<EOHELP
**exec_daemon.php** [__options__] __daemon__ ...
    Run an instance of __daemon__.
EOHELP
  );
$args->parse(
  array(
    array(
      'name' => 'trace',
      'help' => pht('Enable debug tracing.'),
    ),
    array(
      'name' => 'trace-memory',
      'help' => pht('Enable debug memory tracing.'),
    ),
    array(
      'name' => 'verbose',
      'help'  => pht('Enable verbose activity logging.'),
    ),
    array(
      'name' => 'label',
      'short' => 'l',
      'param' => 'label',
      'help' => pht(
        'Optional process label. Makes "%s" nicer, no behavioral effects.',
        'ps'),
    ),
    array(
      'name'     => 'daemon',
      'wildcard' => true,
    ),
  ));

$trace_memory = $args->getArg('trace-memory');
$trace_mode = $args->getArg('trace') || $trace_memory;
$verbose = $args->getArg('verbose');

if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
  fprintf(STDERR, pht('Reading daemon configuration from stdin...')."\n");
}
$config = @file_get_contents('php://stdin');
$config = id(new PhutilJSONParser())->parse($config);

PhutilTypeSpec::checkMap(
  $config,
  array(
    'log' => 'optional string|null',
    'argv' => 'optional list<wild>',
    'load' => 'optional list<string>',
    'down' => 'optional int',
  ));

$log = idx($config, 'log');

if ($log) {
  ini_set('error_log', $log);
  PhutilErrorHandler::setErrorListener(array('PhutilDaemon', 'errorListener'));
}

$load = idx($config, 'load', array());
foreach ($load as $library) {
  $library = Filesystem::resolvePath($library);
  phutil_load_library($library);
}

PhutilErrorHandler::initialize();

$daemon = $args->getArg('daemon');
if (!$daemon) {
  throw new PhutilArgumentUsageException(
    pht('Specify which class of daemon to start.'));
} else if (count($daemon) > 1) {
  throw new PhutilArgumentUsageException(
    pht('Specify exactly one daemon to start.'));
} else {
  $daemon = head($daemon);
  if (!class_exists($daemon)) {
    throw new PhutilArgumentUsageException(
      pht(
        'No class "%s" exists in any known library.',
        $daemon));
  } else if (!is_subclass_of($daemon, 'PhutilDaemon')) {
    throw new PhutilArgumentUsageException(
      pht(
        'Class "%s" is not a subclass of "%s".',
        $daemon,
        'PhutilDaemon'));
  }
}

$argv = idx($config, 'argv', array());
$daemon = newv($daemon, array($argv));

if ($trace_mode) {
  $daemon->setTraceMode();
}

if ($trace_memory) {
  $daemon->setTraceMemory();
}

if ($verbose) {
  $daemon->setVerbose(true);
}

$down_duration = idx($config, 'down');
if ($down_duration) {
  $daemon->setScaledownDuration($down_duration);
}

$daemon->execute();
