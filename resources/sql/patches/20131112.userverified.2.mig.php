<?php

$table = new PhabricatorUser();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $user) {
  $username = $user->getUsername();
  echo pht('Migrating %s...', $username)."\n";
  if ($user->getIsEmailVerified()) {
    // Email already verified.
    continue;
  }

  $primary = $user->loadPrimaryEmail();
  if (!$primary) {
    // No primary email.
    continue;
  }

  if (!$primary->getIsVerified()) {
    // Primary email not verified.
    continue;
  }

  // Primary email is verified, so mark the account as verified.
  queryfx(
    $conn_w,
    'UPDATE %T SET isEmailVerified = 1 WHERE id = %d',
    $table->getTableName(),
    $user->getID());
}

echo pht('Done.')."\n";
