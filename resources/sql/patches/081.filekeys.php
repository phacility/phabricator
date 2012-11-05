<?php

echo "Generating file keys...\n";
$files = id(new PhabricatorFile())->loadAllWhere('secretKey IS NULL');
echo count($files).' files to generate keys for';
foreach ($files as $file) {
  queryfx(
    $file->establishConnection('r'),
    'UPDATE %T SET secretKey = %s WHERE id = %d',
    $file->getTableName(),
    $file->generateSecretKey(),
    $file->getID());
  echo '.';
}
echo "\nDone.\n";
