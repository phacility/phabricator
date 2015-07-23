<?php

echo pht('Migrating task revisions to edges...')."\n";
$table = new ManiphestTask();
$table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $task) {
  $id = $task->getID();
  echo pht('Task %d: ', $id);

  $revs = $task->getAttachedPHIDs(DifferentialRevisionPHIDType::TYPECONST);
  if (!$revs) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  foreach ($revs as $rev) {
    $editor->addEdge(
      $task->getPHID(),
      ManiphestTaskHasRevisionEdgeType::EDGECONST,
      $rev);
  }
  $editor->save();
  echo pht('OKAY')."\n";
}

echo pht('Done.')."\n";
