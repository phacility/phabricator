<?php

echo "Migrating task revisions to edges...\n";
$table = new ManiphestTask();
$table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $task) {
  $id = $task->getID();
  echo "Task {$id}: ";

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
  echo "OKAY\n";
}

echo "Done.\n";
