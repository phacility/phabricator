<?php

echo "Migrating differential dependencies to edges...\n";
foreach (new LiskMigrationIterator(new DifferentialRevision()) as $rev) {
  $id = $rev->getID();
  echo "Revision {$id}: ";

  $deps = $rev->getAttachedPHIDs(PhabricatorPHIDConstants::PHID_TYPE_DREV);
  if (!$deps) {
    echo "-\n";
    continue;
  }

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($deps as $dep) {
    $editor->addEdge(
      $rev->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV,
      $dep);
  }
  $editor->save();
  echo "OKAY\n";
}

echo "Done.\n";
