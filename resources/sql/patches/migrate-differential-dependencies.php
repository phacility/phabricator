<?php

echo "Migrating differential dependencies to edges...\n";
$table = new DifferentialRevision();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $rev) {
  $id = $rev->getID();
  echo "Revision {$id}: ";

  $deps = $rev->getAttachedPHIDs(DifferentialRevisionPHIDType::TYPECONST);
  if (!$deps) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  foreach ($deps as $dep) {
    $editor->addEdge(
      $rev->getPHID(),
      DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST,
      $dep);
  }
  $editor->save();
  echo "OKAY\n";
}

$table->saveTransaction();
echo "Done.\n";
