<?php

echo "Backfilling commit summaries...\n";

$commits = new LiskMigrationIterator(new PhabricatorRepositoryCommit());
foreach ($commits as $commit) {
  echo 'Filling Commit #'.$commit->getID()."\n";

  if (strlen($commit->getSummary())) {
    continue;
  }

  $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
    'commitID = %d',
    $commit->getID());

  if (!$data) {
    continue;
  }

  $commit->setSummary($data->getSummary());
  $commit->save();
}

echo "Done.\n";
