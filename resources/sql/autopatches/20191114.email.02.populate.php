<?php

$table = new PhabricatorUserEmail();
$conn = $table->establishConnection('w');

$iterator = new LiskRawMigrationIterator($conn, $table->getTableName());
foreach ($iterator as $row) {
  $phid = $row['phid'];

  if (!strlen($phid)) {
    queryfx(
      $conn,
      'UPDATE %R SET phid = %s WHERE id = %d',
      $table,
      $table->generatePHID(),
      $row['id']);
  }
}
