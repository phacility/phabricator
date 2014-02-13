<?php

echo "Adding mailkeys to macros.\n";

$table = new PhabricatorFileImageMacro();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $macro) {
  $id = $macro->getID();

  echo "Populating macro {$id}...\n";

  if (!$macro->getMailKey()) {
    queryfx(
      $conn_w,
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $table->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
  }
}

echo "Done.\n";
