<?php

$table = new PhabricatorUserPreferences();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $row) {
  if ($row->getPHID() !== '') {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $table->generatePHID(),
    $row->getID());
}
