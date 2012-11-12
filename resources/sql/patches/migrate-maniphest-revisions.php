<?php

echo "Migrating task revisions to edges...\n";
foreach (new LiskMigrationIterator(new ManiphestTask()) as $task) {
  $id = $task->getID();
  echo "Task {$id}: ";

  $revs = $task->getAttachedPHIDs(PhabricatorPHIDConstants::PHID_TYPE_DREV);
  if (!$revs) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($revs as $rev) {
    $editor->addEdge(
      $task->getPHID(),
      PhabricatorEdgeConfig::TYPE_TASK_HAS_RELATED_DREV,
      $rev);
  }
  $editor->save();
  echo "OKAY\n";
}

echo "Done.\n";
