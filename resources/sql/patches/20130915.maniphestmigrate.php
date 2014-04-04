<?php

$conn_w = id(new ManiphestTask())->establishConnection('w');
$table_name = id(new ManiphestCustomFieldStorage())->getTableName();

$rows = new LiskRawMigrationIterator($conn_w, 'maniphest_taskauxiliarystorage');

echo "Migrating custom storage for Maniphest fields...\n";
foreach ($rows as $row) {
  $phid = $row['taskPHID'];
  $name = $row['name'];

  echo "Migrating {$phid} / {$name}...\n";

  queryfx(
    $conn_w,
    'INSERT IGNORE INTO %T (objectPHID, fieldIndex, fieldValue)
      VALUES (%s, %s, %s)',
    $table_name,
    $phid,
    PhabricatorHash::digestForIndex('std:maniphest:'.$name),
    $row['value']);
}

echo "Done.\n";
