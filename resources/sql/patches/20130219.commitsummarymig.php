<?php

echo pht('Backfilling commit summaries...')."\n";

$table = new PhabricatorRepositoryCommit();
$conn_w = $table->establishConnection('w');
$commits = new LiskMigrationIterator($table);
foreach ($commits as $commit) {
  echo pht('Filling Commit #%d', $commit->getID())."\n";

  if (strlen($commit->getSummary())) {
    continue;
  }

  $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
    'commitID = %d',
    $commit->getID());

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

echo pht('Done.')."\n";
