<?php

echo "Generating file keys...\n";
$table = new PhabricatorFile();
$table->openTransaction();
$table->beginReadLocking();

$files = $table->loadAllWhere('secretKey IS NULL');
echo count($files).' files to generate keys for';
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
echo "\nDone.\n";
