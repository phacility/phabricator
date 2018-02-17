<?php

$table = new HarbormasterBuildable();
$conn = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $buildable) {
  if ($buildable->getBuildableStatus() !== 'building') {
    continue;
  }

  $aborted = queryfx_one(
    $conn,
    'SELECT * FROM %T WHERE buildablePHID = %s AND buildStatus = %s
      LIMIT 1',
    id(new HarbormasterBuild())->getTableName(),
    $buildable->getPHID(),
    'aborted');
  if (!$aborted) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET buildableStatus = %s WHERE id = %d',
    $table->getTableName(),
    'failed',
    $buildable->getID());
}
