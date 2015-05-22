<?php

echo pht('Giving image macros PHIDs');
$table = new PhabricatorFileImageMacro();
$table->openTransaction();

foreach (new LiskMigrationIterator($table) as $macro) {
  if ($macro->getPHID()) {
    continue;
  }

  echo '.';

  queryfx(
    $macro->establishConnection('w'),
    'UPDATE %T SET phid = %s WHERE id = %d',
    $macro->getTableName(),
    $macro->generatePHID(),
    $macro->getID());
}

$table->saveTransaction();
echo "\n".pht('Done.')."\n";
