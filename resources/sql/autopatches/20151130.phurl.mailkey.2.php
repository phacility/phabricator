<?php

$table = new PhabricatorPhurlURL();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $url) {
  $id = $url->getID();

  echo pht('Adding mail key for Phurl %d...', $id);
  echo "\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}
