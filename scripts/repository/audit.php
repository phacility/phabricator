#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage open Audit requests');
$args->setSynopsis(<<<EOSYNOPSIS
**audit.php** __repository_callsign__
    Close all open audit requests in a repository. This is intended to
    reset the state of an imported repository which triggered a bunch of
    spurious audit requests during import.

EOSYNOPSIS
  );
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'more',
      'wildcard'  => true,
    ),
  ));

$more = $args->getArg('more');
if (count($more) !== 1) {
  $args->printHelpAndExit();
}
$callsign = reset($more);

$repository = id(new PhabricatorRepository())->loadOneWhere(
  'callsign = %s',
  $callsign);
if (!$repository) {
  throw new Exception("No repository exists with callsign '{$callsign}'!");
}

$ok = phutil_console_confirm(
  'This will reset all open audit requests ("Audit Required" or "Concern '.
  'Raised") for commits in this repository to "Audit Not Required". This '.
  'operation destroys information and can not be undone! Are you sure '.
  'you want to proceed?');
if (!$ok) {
  echo "OK, aborting.\n";
  die(1);
}

echo "Loading commits...\n";
$all_commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
  'repositoryID = %d',
  $repository->getID());

echo "Clearing audit requests...\n";

foreach ($all_commits as $commit) {
  $query = id(new PhabricatorAuditQuery())
    ->withStatus(PhabricatorAuditQuery::STATUS_OPEN)
    ->withCommitPHIDs(array($commit->getPHID()));
  $requests = $query->execute();

  echo "Clearing ".$commit->getPHID()."... ";

  if (!$requests) {
    echo "nothing to do.\n";
    continue;
  } else {
    echo count($requests)." requests to clear";
  }

  foreach ($requests as $request) {
    $request->setAuditStatus(
      PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED);
    $request->save();
    echo ".";
  }

  $commit->setAuditStatus(PhabricatorAuditCommitStatusConstants::NONE);
  $commit->save();
  echo "\n";
}

echo "Done.\n";
