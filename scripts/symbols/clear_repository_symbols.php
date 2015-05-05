#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setSynopsis(<<<EOSYNOPSIS
**clear_repository_symbols.php** [__options__] __callsign__

  Clear repository symbols.
EOSYNOPSIS
  );
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'callsign',
      'wildcard'  => true,
    ),
  ));

$callsigns = $args->getArg('callsign');
if (count($callsigns) !== 1) {
  $args->printHelpAndExit();
}

$callsign = head($callsigns);
$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withCallsigns($callsigns)
  ->executeOne();

if (!$repository) {
  echo pht("Repository '%s' does not exist.", $callsign);
  exit(1);
}

$input = file_get_contents('php://stdin');
$normalized = array();
foreach (explode("\n", trim($input)) as $path) {
  // Emulate the behavior of the symbol generation scripts.
  $normalized[] = '/'.ltrim($path, './');
}
$paths = PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
  $normalized);

$symbol = new PhabricatorRepositorySymbol();
$conn_w = $symbol->establishConnection('w');

foreach (array_chunk(array_values($paths), 128) as $chunk) {
  queryfx(
    $conn_w,
    'DELETE FROM %T WHERE repositoryPHID = %s AND pathID IN (%Ld)',
    $symbol->getTableName(),
    $repository->getPHID(),
    $chunk);
}
