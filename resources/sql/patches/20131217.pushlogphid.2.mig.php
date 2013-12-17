<?php

$table = new PhabricatorRepositoryPushLog();
$conn_w = $table->establishConnection('w');

echo "Assigning PHIDs to push logs...\n";

$logs = new LiskMigrationIterator($table);
foreach ($logs as $log) {
  $id = $log->getID();
  echo "Updating {$id}...\n";
  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $log->generatePHID(),
    $id);
}

echo "Done.\n";
