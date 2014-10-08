<?php

$table = new LegalpadDocumentSignature();
$conn_w = $table->establishConnection('w');
foreach (new LiskMigrationIterator($table) as $signature) {
  echo pht("Updating Legalpad signature %d...\n", $signature->getID());

  $data = $signature->getSignatureData();

  queryfx(
    $conn_w,
    'UPDATE %T SET signerName = %s, signerEmail = %s WHERE id = %d',
    $table->getTableName(),
    (string)idx($data, 'name'),
    (string)idx($data, 'email'),
    $signature->getID());
}
