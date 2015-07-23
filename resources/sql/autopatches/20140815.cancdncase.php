<?php

// This corrects files which incorrectly had a 'cancdn' property written;
// the property should be 'canCDN'.

$table = new PhabricatorFile();
$conn_w = $table->establishConnection('w');
foreach (new LiskMigrationIterator($table) as $file) {
  $id = $file->getID();
  echo pht(
    "Updating capitalization of %s property for file %d...\n",
    'canCDN',
    $id);
  $meta = $file->getMetadata();

  if (isset($meta['cancdn'])) {
    $meta['canCDN'] = $meta['cancdn'];
    unset($meta['cancdn']);

    queryfx(
      $conn_w,
      'UPDATE %T SET metadata = %s WHERE id = %d',
      $table->getTableName(),
      json_encode($meta),
      $id);
  }
}
