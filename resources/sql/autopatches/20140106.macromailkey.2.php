<?php

echo pht('Adding mailkeys to macros.')."\n";

$table = new PhabricatorFileImageMacro();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $macro) {
  $id = $macro->getID();

  echo pht('Populating macro %d...', $id)."\n";

  if (!$macro->getMailKey()) {
    queryfx(
      $conn_w,
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $table->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
  }
}

echo pht('Done.')."\n";
