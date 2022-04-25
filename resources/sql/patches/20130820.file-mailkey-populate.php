<?php

echo pht('Populating files with mail keys...')."\n";

$table = new PhabricatorFile();
$table_name = $table->getTableName();

$conn_w = $table->establishConnection('w');
$conn_w->openTransaction();

$sql = array();
foreach (new LiskRawMigrationIterator($conn_w, 'file') as $row) {
  // NOTE: MySQL requires that the INSERT specify all columns which don't
  // have default values when configured in strict mode. This query will
  // never actually insert rows, but we need to hand it values anyway.

  $sql[] = qsprintf(
    $conn_w,
    '(%d, %s, 0, 0, 0, 0, 0, 0, 0, 0)',
    $row['id'],
    Filesystem::readRandomCharacters(20));
}

if ($sql) {
  foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
    queryfx(
      $conn_w,
      'INSERT INTO %T
        (id, mailKey, phid, byteSize, storageEngine, storageFormat,
          storageHandle, dateCreated, dateModified, metadata) VALUES %LQ '.
      'ON DUPLICATE KEY UPDATE mailKey = VALUES(mailKey)',
      $table_name,
      $chunk);
  }
}

$table->saveTransaction();
echo pht('Done.')."\n";
