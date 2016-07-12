<?php

$conn_w = id(new DifferentialRevision())->establishConnection('w');
$rows = new LiskRawMigrationIterator($conn_w, 'differential_auxiliaryfield');

echo pht('Modernizing Differential auxiliary field storage...')."\n";

$table_name = id(new DifferentialCustomFieldStorage())->getTableName();
foreach ($rows as $row) {
  $id = $row['id'];
  echo pht('Migrating row %d...', $id)."\n";
  queryfx(
    $conn_w,
    'INSERT IGNORE INTO %T (objectPHID, fieldIndex, fieldValue)
      VALUES (%s, %s, %s)',
    $table_name,
    $row['revisionPHID'],
    PhabricatorHash::digestForIndex($row['name']),
    $row['value']);
}

echo pht('Done.')."\n";
