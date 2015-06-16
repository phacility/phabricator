<?php

$table = new PhabricatorRepositoryPushLog();
$conn_w = $table->establishConnection('w');

echo pht('Assigning PHIDs to push logs...')."\n";

$logs = new LiskMigrationIterator($table);
foreach ($logs as $log) {
  $id = $log->getID();
  echo pht('Updating %s...', $id)."\n";
  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $log->generatePHID(),
    $id);
}

echo pht('Done.')."\n";
