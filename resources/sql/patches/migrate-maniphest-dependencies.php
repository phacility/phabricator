<?php

echo pht('Migrating task dependencies to edges...')."\n";
$table = new ManiphestTask();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $task) {
  $id = $task->getID();
  echo pht('Task %d: ', $id);

  $deps = $task->getAttachedPHIDs(ManiphestTaskPHIDType::TYPECONST);
  if (!$deps) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  foreach ($deps as $dep) {
    $editor->addEdge(
      $task->getPHID(),
      ManiphestTaskDependsOnTaskEdgeType::EDGECONST,
      $dep);
  }
  $editor->save();
  echo pht('OKAY')."\n";
}

$table->saveTransaction();
echo pht('Done.')."\n";
