<?php

$table = new PhabricatorAuthSSHKey();
$conn_w = $table->establishConnection('w');

echo "Updating SSH public key indexes...\n";

$keys = new LiskMigrationIterator($table);
foreach ($keys as $key) {
  $id = $key->getID();

  echo "Updating key {$id}...\n";

  try {
    $hash = $key->toPublicKey()->getHash();
  } catch (Exception $ex) {
    echo "Key has bad format! Removing key.\n";
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE id = %d',
      $table->getTableName(),
      $id);
    continue;
  }

  $collision = queryfx_all(
    $conn_w,
    'SELECT * FROM %T WHERE keyIndex = %s AND id < %d',
    $table->getTableName(),
    $hash,
    $key->getID());
  if ($collision) {
    echo "Key is a duplicate! Removing key.\n";
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE id = %d',
      $table->getTableName(),
      $id);
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET keyIndex = %s WHERE id = %d',
    $table->getTableName(),
    $hash,
    $key->getID());
}

echo "Done.\n";
