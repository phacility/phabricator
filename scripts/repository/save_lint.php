#!/usr/bin/env php
<?php

require_once dirname(__FILE__).'/../__init_script__.php';

if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
  $command = 'xargs -0 arc lint --output json | '.__FILE__;
  echo "Usage: git ls-files -z | {$command}\n";
  echo "Usage: git diff --name-only -z | {$command}\n"; // TODO: Handle deletes.
  echo "Purpose: Save all lint errors to database.\n";
  exit(1);
}

$working_copy = ArcanistWorkingCopyIdentity::newFromPath('.');
$api = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity($working_copy);
$svn_root = id(new PhutilURI($api->getSourceControlPath()))->getPath();

$project_id = $working_copy->getProjectID();
$project = id(new PhabricatorRepositoryArcanistProject())
  ->loadOneWhere('name = %s', $project_id);
if (!$project || !$project->getRepositoryID()) {
  throw new Exception("Couldn't find repository for {$project_id}.");
}

$branch_name = $api->getBranchName();
$branch = id(new PhabricatorRepositoryBranch())->loadOneWhere(
  'repositoryID = %d AND name = %s',
  $project->getRepositoryID(),
  $branch_name);
if (!$branch) {
  $branch = id(new PhabricatorRepositoryBranch())
    ->setRepositoryID($project->getRepositoryID())
    ->setName($branch_name);
}
$branch->setLintCommit($api->getWorkingCopyRevision());
$branch->save();
$conn = $branch->establishConnection('w');

$inserts = array();

while ($json = fgets(STDIN)) {
  $paths = json_decode(rtrim($json, "\n"), true);
  if (!is_array($paths)) {
    throw new Exception("Invalid JSON: {$json}");
  }

  if (!$paths) {
    continue;
  }

  $conn->openTransaction();

  foreach (array_chunk(array_keys($paths), 1024) as $some_paths) {
    $full_paths = array();
    foreach ($some_paths as $path) {
      $full_paths[] = $svn_root.'/'.$path;
    }
    queryfx(
      $conn,
      'DELETE FROM %T WHERE branchID = %d AND path IN (%Ls)',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      $branch->getID(),
      $full_paths);
  }

  foreach ($paths as $path => $messages) {
    // TODO: Handle multiple $json for a single path. Don't save duplicates.

    foreach ($messages as $message) {
      $inserts[] = qsprintf(
        $conn,
        '(%d, %s, %d, %s, %s, %s, %s)',
        $branch->getID(),
        $svn_root.'/'.$path,
        idx($message, 'line', 0),
        idx($message, 'code', ''),
        idx($message, 'severity', ''),
        idx($message, 'name', ''),
        idx($message, 'description', ''));

      if (count($inserts) >= 256) {
        save_lint_messages($conn, $inserts);
        $inserts = array();
      }
    }
  }

  $conn->saveTransaction();
}

save_lint_messages($conn, $inserts);

function save_lint_messages($conn, array $inserts) {
  if ($inserts) {
    queryfx(
      $conn,
      'INSERT INTO %T
        (branchID, path, line, code, severity, name, description)
        VALUES %Q',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      implode(', ', $inserts));
  }
}
