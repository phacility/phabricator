<?php

echo "Migrating task dependencies to edges...\n";
$table = new ManiphestTask();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $task) {
  $id = $task->getID();
  echo "Task {$id}: ";

  $deps = $task->getAttachedPHIDs(PhabricatorPHIDConstants::PHID_TYPE_TASK);
  if (!$deps) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($deps as $dep) {
    $editor->addEdge(
      $task->getPHID(),
      PhabricatorEdgeConfig::TYPE_TASK_DEPENDS_ON_TASK,
      $dep);
  }
  $editor->save();
  echo "OKAY\n";
}

$table->saveTransaction();
echo "Done.\n";
