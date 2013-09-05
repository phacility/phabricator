<?php

echo "Populating Phabricator files with mail keys xactions...\n";

$table = new PhabricatorFile();
$table_name = $table->getTableName();

$conn_w = $table->establishConnection('w');
$conn_w->openTransaction();

$sql = array();
foreach (new LiskRawMigrationIterator($conn_w, 'file') as $row) {
  $sql[] = qsprintf(
    $conn_w,
    '(%d, %s)',
    $row['id'],
    Filesystem::readRandomCharacters(20));
}

if ($sql) {
  foreach (PhabricatorLiskDAO::chunkSQL($sql, ', ') as $chunk) {
    queryfx(
      $conn_w,
      'INSERT INTO %T (id, mailKey) VALUES %Q '.
      'ON DUPLICATE KEY UPDATE mailKey = VALUES(mailKey)',
      $table_name,
      $chunk);
  }
}

$table->saveTransaction();
echo "Done.\n";
