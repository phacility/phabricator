<?php

$table = new PhabricatorOwnersPackage();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $package) {
  $id = $package->getID();

  echo pht('Adding mail key for package %d...', $id);
  echo "\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}
