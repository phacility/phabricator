<?php

$table = new PhabricatorProject();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $project) {
  $id = $project->getID();

  echo pht('Adding mail key for project %d...', $id);
  echo "\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}
