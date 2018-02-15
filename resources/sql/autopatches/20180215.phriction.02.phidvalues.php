<?php

$table = new PhrictionContent();
$conn = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $row) {
  if (strlen($row->getPHID())) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $table->generatePHID(),
    $row->getID());
}
