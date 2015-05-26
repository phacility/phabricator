<?php

echo pht('Giving Pholio images PHIDs');
$table = new PholioImage();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $image) {
  if ($image->getPHID()) {
    continue;
  }

  echo '.';

  queryfx(
    $image->establishConnection('w'),
    'UPDATE %T SET phid = %s WHERE id = %d',
    $image->getTableName(),
    $image->generatePHID(),
    $image->getID());
}

$table->saveTransaction();
echo "\n".pht('Done.')."\n";
