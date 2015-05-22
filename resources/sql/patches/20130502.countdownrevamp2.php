<?php

echo pht('Giving countdowns PHIDs');
$table = new PhabricatorCountdown();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $countdown) {
  if ($countdown->getPHID()) {
    continue;
  }

  echo '.';

  queryfx(
    $countdown->establishConnection('w'),
    'UPDATE %T SET phid = %s WHERE id = %d',
    $countdown->getTableName(),
    $countdown->generatePHID(),
    $countdown->getID());
}

$table->saveTransaction();
echo "\n".pht('Done.')."\n";
