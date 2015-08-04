<?php

$table = new PonderAnswer();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $answer) {
  $id = $answer->getID();

  echo pht('Adding mail key for Answer %d...', $id);
  echo "\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}
