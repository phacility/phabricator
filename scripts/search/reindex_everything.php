#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

// TODO: Get rid of this script eventually, once this stuff is better-formalized
// in Timeline consumers.

echo "Reindexing revisions...\n";
$revs = new LiskMigrationIterator(new DifferentialRevision());
foreach ($revs as $rev) {
  PhabricatorSearchDifferentialIndexer::indexRevision($rev);
  echo '.';
}
echo "\n";

echo "Reindexing commits...\n";
$commits = new LiskMigrationIterator(new PhabricatorRepositoryCommit());
foreach ($commits as $commit) {
  PhabricatorSearchCommitIndexer::indexCommit($commit);
  echo '.';
}
echo "\n";

echo "Reindexing tasks...\n";
$tasks = new LiskMigrationIterator(new ManiphestTask());
foreach ($tasks as $task) {
  PhabricatorSearchManiphestIndexer::indexTask($task);
  echo '.';
}
echo "\n";

include dirname(__FILE__).'/reindex_all_users.php';
