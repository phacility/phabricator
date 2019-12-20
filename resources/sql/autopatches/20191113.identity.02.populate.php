<?php

$table = new PhabricatorRepositoryIdentity();
$conn = $table->establishConnection('w');

$iterator = new LiskRawMigrationIterator($conn, $table->getTableName());
foreach ($iterator as $row) {
  $name = $row['identityNameRaw'];
  $name = phutil_utf8ize($name);

  $email = new PhutilEmailAddress($name);
  $address = $email->getAddress();

  try {
    queryfx(
      $conn,
      'UPDATE %R SET emailAddress = %ns WHERE id = %d',
      $table,
      $address,
      $row['id']);
  } catch (Exception $ex) {
    // We may occasionally run into issues with binary or very long addresses.
    // Just skip over them.
   continue;
  }
}
