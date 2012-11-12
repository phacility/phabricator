#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('reconcile Phabricator state after repository changes');
$args->setSynopsis(<<<EOSYNOPSIS
**reconcile.php** __repository_callsign__
    Reconcile the state of Phabricator's caches with the actual state
    of the repository.

    This is an administrative/maintenace operation and not generally
    necessary, but if repository history has changed or been rewritten
    (for example, if the repository was stored from a backup)
    Phabricator may think commits which are no longer present in the
    repository still exist.

    This will delete all evidence of commits which Phabricator can't
    find in the actual repository.

EOSYNOPSIS
  );
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name' => 'more',
      'wildcard' => true,
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

switch ($repository->getVersionControlSystem()) {
  case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
    break;
  case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
  case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
  default:
    throw new Exception("For now, you can only reconcile git repositories.");
}

echo "Loading commits...\n";
$all_commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
  'repositoryID = %d',
  $repository->getID());

echo "Updating repository..\n";
try {
  // Sanity-check the repository working copy and make sure we're up to date.
  $repository->execxLocalCommand('fetch --all');
} catch (Exception $ex) {
  echo "Unable to `git fetch` the working copy to update it. Reconciliation ".
       "requires an up-to-date working copy.\n";
  throw $ex;
}

echo "Verifying commits (this may take some time if the repository is large)";
$futures = array();
foreach ($all_commits as $id => $commit) {
  // NOTE: We use "cat-file -t", not "rev-parse --verify", because
  // "rev-parse --verify" does not verify that the object actually exists, only
  // that the name is properly formatted.
  $futures[$id] = $repository->getLocalCommandFuture(
    'cat-file -t %s',
    $commit->getCommitIdentifier());
}

$bad = array();
foreach (Futures($futures)->limit(8) as $id => $future) {
  list($err) = $future->resolve();
  if ($err) {
    $bad[$id] = $all_commits[$id];
    echo "#";
  } else {
    echo ".";
  }
}
echo "\nDone.\n";

if (!count($bad)) {
  echo "No bad commits found!\n";
} else {
  echo "Found ".count($bad)." bad commits:\n\n";
  echo '    '.implode("\n    ", mpull($bad, 'getCommitIdentifier'));
  $ok = phutil_console_confirm("Do you want to delete these commits?");
  if (!$ok) {
    echo "OK, aborting.\n";
    exit(1);
  }

  echo "Deleting commits";
  foreach ($bad as $commit) {
    echo ".";
    $commit->delete();
  }
  echo "\nDone.\n";
}

////   Clean Up Links   ////////////////////////////////////////////////////////

$table = new PhabricatorRepositoryCommit();

$valid_phids = queryfx_all(
  $table->establishConnection('r'),
  'SELECT phid FROM %T',
  $table->getTableName());
$valid_phids = ipull($valid_phids, null, 'phid');

////////   Differential <-> Diffusion Links   //////////////////////////////////

$dx_conn = id(new DifferentialRevision())->establishConnection('w');
$dx_table = DifferentialRevision::TABLE_COMMIT;
$dx_phids = queryfx_all(
  $dx_conn,
  'SELECT commitPHID FROM %T',
  $dx_table);

$bad_phids = array();
foreach ($dx_phids as $dx_phid) {
  if (empty($valid_phids[$dx_phid['commitPHID']])) {
    $bad_phids[] = $dx_phid['commitPHID'];
  }
}

if ($bad_phids) {
  echo "Deleting ".count($bad_phids)." bad Diffusion links...\n";
  queryfx(
    $dx_conn,
    'DELETE FROM %T WHERE commitPHID IN (%Ls)',
    $dx_table,
    $bad_phids);
  echo "Done.\n";
} else {
  echo "Diffusion links are clean.\n";
}

// TODO: There are some links in owners that we should probably clean up too.
