<?php

$table = new PhabricatorOwnersPath();
$conn = $table->establishConnection('w');

$seen = array();
foreach (new LiskMigrationIterator($table) as $path) {
  $package_id = $path->getPackageID();
  $repository_phid = $path->getRepositoryPHID();
  $path_index = $path->getPathIndex();

  if (!isset($seen[$package_id][$repository_phid][$path_index])) {
    $seen[$package_id][$repository_phid][$path_index] = true;
    continue;
  }

  queryfx(
    $conn,
    'DELETE FROM %T WHERE id = %d',
    $table->getTableName(),
    $path->getID());
}
