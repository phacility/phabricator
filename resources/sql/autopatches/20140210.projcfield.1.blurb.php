<?php

$conn_w = id(new PhabricatorProject())->establishConnection('w');
$table_name = id(new PhabricatorProjectCustomFieldStorage())->getTableName();

$rows = new LiskRawMigrationIterator($conn_w, 'project_profile');

echo pht('Migrating project descriptions to custom storage...')."\n";
foreach ($rows as $row) {
  $phid = $row['projectPHID'];

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

echo pht('Done.')."\n";
