<?php

echo "Backfilling commit summaries...\n";

$table = new PhabricatorRepositoryCommit();
$conn_w = $table->establishConnection('w');
$commits = new LiskMigrationIterator($table);
foreach ($commits as $commit) {
  echo 'Filling Commit #'.$commit->getID()."\n";

  if (strlen($commit->getSummary())) {
    continue;
  }

  $data = $commit->loadOneRelative(
    new PhabricatorRepositoryCommitData(),
    'commitID');

  if (!$data) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET summary = %s WHERE id = %d',
    $commit->getTableName(),
    $data->getSummary(),
    $commit->getID());
}

echo "Done.\n";
