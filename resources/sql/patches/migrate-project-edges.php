<?php

echo "Migrating project members to edges...\n";
foreach (new LiskMigrationIterator(new PhabricatorProject()) as $proj) {
  $id = $proj->getID();
  echo "Project {$id}: ";

  $members = queryfx_all(
    $proj->establishConnection('r'),
    'SELECT userPHID FROM %T WHERE projectPHID = %s',
    'project_affiliation',
    $proj->getPHID());

  if (!$members) {
    echo "-\n";
    continue;
  }

  $members = ipull($members, 'userPHID');

  $editor = new PhabricatorEdgeEditor();
  $editor->setSuppressEvents(true);
  foreach ($members as $user_phid) {
    $editor->addEdge(
      $proj->getPHID(),
      PhabricatorEdgeConfig::TYPE_PROJ_MEMBER,
      $user_phid);
  }
  $editor->save();
  echo "OKAY\n";
}

echo "Done.\n";
