<?php

$table = new PhabricatorFile();
$conn_w = $table->establishConnection('w');
foreach (new LiskMigrationIterator($table) as $file) {
  $id = $file->getID();
  echo "Updating flags for file {$id}...\n";
  $meta = $file->getMetadata();
  if (!idx($meta, 'canCDN')) {

    $meta['canCDN'] = true;

    queryfx(
      $conn_w,
      'UPDATE %T SET metadata = %s WHERE id = %d',
      $table->getTableName(),
      json_encode($meta),
      $id);
  }
}
