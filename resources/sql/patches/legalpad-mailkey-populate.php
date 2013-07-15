<?php

echo "Populating Legalpad Documents with mail keys...\n";
$table = new LegalpadDocument();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $document) {
  $id = $document->getID();

  echo "Document {$id}: ";
  if (!$document->getMailKey()) {
    queryfx(
      $document->establishConnection('w'),
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $document->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
    echo "Generated Key\n";
  } else {
    echo "-\n";
  }
}

$table->saveTransaction();
echo "Done.\n";
