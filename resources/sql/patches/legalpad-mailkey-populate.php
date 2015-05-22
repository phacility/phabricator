<?php

echo pht('Populating Legalpad Documents with mail keys...')."\n";
$table = new LegalpadDocument();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $document) {
  $id = $document->getID();

  echo pht('Document %s: ', $id);
  if (!$document->getMailKey()) {
    queryfx(
      $document->establishConnection('w'),
      'UPDATE %T SET mailKey = %s WHERE id = %d',
      $document->getTableName(),
      Filesystem::readRandomCharacters(20),
      $id);
    echo pht('Generated Key')."\n";
  } else {
    echo "-\n";
  }
}

$table->saveTransaction();
echo pht('Done.')."\n";
