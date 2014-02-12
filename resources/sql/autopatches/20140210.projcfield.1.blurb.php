<?php

$conn_w = id(new PhabricatorProject())->establishConnection('w');
$table_name = id(new PhabricatorProjectCustomFieldStorage())->getTableName();

$rows = new LiskRawMigrationIterator($conn_w, 'project_profile');

echo "Migrating project descriptions to custom storage...\n";
foreach ($rows as $row) {
  $phid = $row['projectPHID'];
  echo "Migrating {$phid}...\n";

  $desc = $row['blurb'];
  if (strlen($desc)) {
    queryfx(
      $conn_w,
      'INSERT IGNORE INTO %T (objectPHID, fieldIndex, fieldValue)
        VALUES (%s, %s, %s)',
      $table_name,
      $phid,
      PhabricatorHash::digestForIndex('std:project:internal:description'),
      $desc);
  }
}

echo "Done.\n";
