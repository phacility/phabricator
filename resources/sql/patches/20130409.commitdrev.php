<?php

echo "Migrating differential.revisionPHID to edges...\n";
$commit_table = new PhabricatorRepositoryCommit();
$data_table = new PhabricatorRepositoryCommitData();
$editor = id(new PhabricatorEdgeEditor())->setSuppressEvents(true);
$commit_table->establishConnection('w');
$edges = 0;

foreach (new LiskMigrationIterator($commit_table) as $commit) {
  $data = $commit->loadOneRelative($data_table, 'commitID');
  if (!$data) {
    continue;
  }

  $revision_phid = $data->getCommitDetail('differential.revisionPHID');
  if (!$revision_phid) {
    continue;
  }

  $commit_drev = PhabricatorEdgeConfig::TYPE_COMMIT_HAS_DREV;
  $editor->addEdge($commit->getPHID(), $commit_drev, $revision_phid);
  $edges++;
  if ($edges % 256 == 0) {
    echo ".";
    $editor->save();
    $editor = id(new PhabricatorEdgeEditor())->setSuppressEvents(true);
  }
}

echo ".";
$editor->save();
echo "\nDone.\n";
