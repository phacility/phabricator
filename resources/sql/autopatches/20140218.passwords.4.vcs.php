<?php

$table = new PhabricatorRepositoryVCSPassword();
$conn_w = $table->establishConnection('w');

echo pht('Upgrading password hashing for VCS passwords.')."\n";

$best_hasher = PhabricatorPasswordHasher::getBestHasher();
foreach (new LiskMigrationIterator($table) as $password) {
  $id = $password->getID();

  echo pht('Migrating VCS password %d...', $id)."\n";

  $input_hash = $password->getPasswordHash();
  $input_envelope = new PhutilOpaqueEnvelope($input_hash);

  $storage_hash = $best_hasher->getPasswordHashForStorage($input_envelope);

  queryfx(
    $conn_w,
    'UPDATE %T SET passwordHash = %s WHERE id = %d',
    $table->getTableName(),
    $storage_hash->openEnvelope(),
    $id);
}

echo pht('Done.')."\n";
