<?php

echo pht('Adding secretkeys to legalpad document signatures.')."\n";

$table = new LegalpadDocumentSignature();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $sig) {
  $id = $sig->getID();

  echo pht('Populating signature %d...', $id)."\n";

  if (!$sig->getSecretKey()) {
    queryfx(
      $conn_w,
      'UPDATE %T SET secretKey = %s WHERE id = %d',
      $table->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
  }
}

echo pht('Done.')."\n";
