<?php

echo "Archiving projects with no members...\n";

$table = new PhabricatorProject();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $project) {

  $members = PhabricatorEdgeQuery::loadDestinationPHIDs(
    $project->getPHID(),
    PhabricatorProjectProjectHasMemberEdgeType::EDGECONST);

  if (count($members)) {
    echo sprintf(
      'Project "%s" has %d members; skipping.',
      $project->getName(),
      count($members)), "\n";
    continue;
  }

  if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED) {
    echo sprintf(
      'Project "%s" already archived; skipping.',
      $project->getName()), "\n";
    continue;
  }

  echo sprintf('Archiving project "%s"...', $project->getName()), "\n";
  queryfx(
    $table->establishConnection('w'),
    'UPDATE %T SET status = %s WHERE id = %d',
    $table->getTableName(),
    PhabricatorProjectStatus::STATUS_ARCHIVED,
    $project->getID());
}

$table->saveTransaction();
echo "\nDone.\n";
