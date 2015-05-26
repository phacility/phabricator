<?php

echo pht('Generating file keys...')."\n";
$table = new PhabricatorFile();
$table->openTransaction();
$table->beginReadLocking();

$files = $table->loadAllWhere('secretKey IS NULL');
echo pht('%d files to generate keys for', count($files));
foreach ($files as $file) {
  queryfx(
    $file->establishConnection('w'),
    'UPDATE %T SET secretKey = %s WHERE id = %d',
    $file->getTableName(),
    $file->generateSecretKey(),
    $file->getID());
  echo '.';
}

$table->endReadLocking();
$table->saveTransaction();
echo "\n".pht('Done.')."\n";
