<?php

$table = new PhabricatorBadgesBadge();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $badge) {
  $id = $badge->getID();

  echo pht('Adding mail key for badge %d...', $id);
  echo "\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}
