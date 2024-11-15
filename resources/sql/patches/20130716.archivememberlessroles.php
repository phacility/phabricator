<?php

echo pht('Archiving roles with no members...')."\n";

$table = new PhabricatorRole();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $role) {
  $members = PhabricatorEdgeQuery::loadDestinationPHIDs(
    $role->getPHID(),
    PhabricatorRoleRoleHasMemberEdgeType::EDGECONST);

  if (count($members)) {
    echo pht(
      'Role "%s" has %d members; skipping.',
      $role->getName(),
      count($members)), "\n";
    continue;
  }

  if ($role->getStatus() == PhabricatorRoleStatus::STATUS_ARCHIVED) {
    echo pht(
      'Role "%s" already archived; skipping.',
      $role->getName()), "\n";
    continue;
  }

  echo pht('Archiving role "%s"...', $role->getName())."\n";
  queryfx(
    $table->establishConnection('w'),
    'UPDATE %T SET status = %s WHERE id = %d',
    $table->getTableName(),
    PhabricatorRoleStatus::STATUS_ARCHIVED,
    $role->getID());
}

$table->saveTransaction();
echo "\n".pht('Done.')."\n";
