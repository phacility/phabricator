<?php

$table = new PhabricatorRepositoryRefCursor();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $cursor) {
  if (strlen($cursor->getPHID())) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $table->generatePHID(),
    $cursor->getID());
}
