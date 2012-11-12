#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('reopen reviews accidentally closed by a bad push');
$args->setSynopsis(<<<EOSYNOPSIS
**undo_commits.php** --repository __callsign__ < __commits__

    Reopen code reviews accidentally closed by a bad push. If someone
    pushes a bunch of commits to a tracked branch that they shouldn't
    have, you can pipe in all the commit hashes to this script to
    "undo" the damage in Differential after you revert the commits.

    To use this script:

      1. Identify the commits you want to undo the effects of.
      2. Put all their identifiers (commit hashes in git/hg, revision
         numbers in svn) into a file, one per line.
      3. Pipe that file into this script with relevant arguments.
      4. Revisions marked "closed" by those commits will be
         restored to their previous state.

EOSYNOPSIS
  );
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'    => 'repository',
      'param'   => 'callsign',
      'help'    => 'Callsign for the repository these commits appear in.',
    ),
  ));

$callsign = $args->getArg('repository');
if (!$callsign) {
  $args->printHelpAndExit();
}

$repository = id(new PhabricatorRepository())->loadOneWhere(
  'callsign = %s',
  $callsign);

if (!$repository) {
  throw new Exception("No repository with callsign '{$callsign}'!");
}

echo "Reading commit identifiers from stdin...\n";

$identifiers = @file_get_contents('php://stdin');
$identifiers = trim($identifiers);
$identifiers = explode("\n", $identifiers);

echo "Read ".count($identifiers)." commit identifiers.\n";

if (!$identifiers) {
  throw new Exception("You must provide commmit identifiers on stdin!");
}

echo "Looking up commits...\n";
$commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
  'repositoryID = %d AND commitIdentifier IN (%Ls)',
  $repository->getID(),
  $identifiers);

echo "Found ".count($commits)." matching commits.\n";

if (!$commits) {
  throw new Exception("None of the commits could be found!");
}

$commit_phids = mpull($commits, 'getPHID', 'getPHID');

echo "Looking up revisions marked 'closed' by these commits...\n";
$revision_ids = queryfx_all(
  id(new DifferentialRevision())->establishConnection('r'),
  'SELECT DISTINCT revisionID from %T WHERE commitPHID IN (%Ls)',
  DifferentialRevision::TABLE_COMMIT,
  $commit_phids);
$revision_ids = ipull($revision_ids, 'revisionID');

echo "Found ".count($revision_ids)." associated revisions.\n";
if (!$revision_ids) {
  echo "Done -- nothing to do.\n";
  return;
}

$status_closed = ArcanistDifferentialRevisionStatus::CLOSED;

$revisions = array();
$map = array();

if ($revision_ids) {
  foreach ($revision_ids as $revision_id) {
    echo "Assessing revision D{$revision_id}...\n";
    $revision = id(new DifferentialRevision())->load($revision_id);

    if ($revision->getStatus() != $status_closed) {
      echo "Revision is not 'closed', skipping.\n";
    }

    $assoc_commits = queryfx_all(
      $revision->establishConnection('r'),
      'SELECT commitPHID FROM %T WHERE revisionID = %d',
      DifferentialRevision::TABLE_COMMIT,
      $revision_id);
    $assoc_commits = ipull($assoc_commits, 'commitPHID', 'commitPHID');

    if (array_diff_key($assoc_commits, $commit_phids)) {
      echo "Revision is associated with other commits, skipping.\n";
    }

    $comments = id(new DifferentialComment())->loadAllWhere(
      'revisionID = %d',
      $revision_id);

    $new_status = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
    foreach ($comments as $comment) {
      switch ($comment->getAction()) {
        case DifferentialAction::ACTION_ACCEPT:
          $new_status = ArcanistDifferentialRevisionStatus::ACCEPTED;
          break;
        case DifferentialAction::ACTION_REJECT:
        case DifferentialAction::ACTION_RETHINK:
          $new_status = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;
          break;
        case DifferentialAction::ACTION_ABANDON:
          $new_status = ArcanistDifferentialRevisionStatus::ABANDONED;
          break;
        case DifferentialAction::ACTION_RECLAIM:
        case DifferentialAction::ACTION_UPDATE:
          $new_status = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
          break;
      }
    }

    $revisions[$revision_id] = $revision;
    $map[$revision_id] = $new_status;
  }
}

if (!$revisions) {
  echo "Done -- nothing to do.\n";
}

echo "Found ".count($revisions)." revisions to update:\n\n";
foreach ($revisions as $id => $revision) {

  $old_status = ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
    $revision->getStatus());
  $new_status = ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
    $map[$id]);

  echo "    - D{$id}: ".$revision->getTitle()."\n";
  echo "      Will update: {$old_status} -> {$new_status}\n\n";
}

$ok = phutil_console_confirm('Apply these changes?');
if (!$ok) {
  echo "Aborted.\n";
  exit(1);
}

echo "Saving changes...\n";
foreach ($revisions as $id => $revision) {
  queryfx(
    $revision->establishConnection('r'),
    'UPDATE %T SET status = %d WHERE id = %d',
    $revision->getTableName(),
    $map[$id],
    $id);
}
echo "Done.\n";


