<?php

// See PHI1046. The "spacePHID" column for milestones may have fallen out of
// sync; correct all existing values.

$table = new PhabricatorProject();
$conn = $table->establishConnection('w');
$table_name = $table->getTableName();

foreach (new LiskRawMigrationIterator($conn, $table_name) as $project_row) {
  queryfx(
    $conn,
    'UPDATE %R SET spacePHID = %ns
      WHERE parentProjectPHID = %s AND milestoneNumber IS NOT NULL',
    $table,
    $project_row['spacePHID'],
    $project_row['phid']);
}
