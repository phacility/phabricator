<?php

echo pht('Migrating role members to edges...')."\n";
$table = new PhabricatorRole();
$table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $proj) {
  $id = $proj->getID();
  echo pht('Role %d: ', $id);

  $members = queryfx_all(
    $proj->establishConnection('w'),
    'SELECT userPHID FROM %T WHERE rolePHID = %s',
    'role_affiliation',
    $proj->getPHID());

  if (!$members) {
    echo "-\n";
    continue;
  }

  $members = ipull($members, 'userPHID');

  $editor = new PhabricatorEdgeEditor();
  foreach ($members as $user_phid) {
    $editor->addEdge(
      $proj->getPHID(),
      PhabricatorRoleRoleHasMemberEdgeType::EDGECONST,
      $user_phid);
  }
  $editor->save();
  echo pht('OKAY')."\n";
}

echo pht('Done.')."\n";
