<?php

$table = new PhabricatorUser();
$conn = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $user) {
  // Ignore users who are verified.
  if ($user->getIsEmailVerified()) {
    continue;
  }

  // Ignore unverified users with missing (rare) or unverified (common)
  // primary emails: it's correct that their accounts are not verified.
  $primary = $user->loadPrimaryEmail();
  if (!$primary) {
    continue;
  }

  if (!$primary->getIsVerified()) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET isEmailVerified = 1 WHERE id = %d',
    $table->getTableName(),
    $user->getID());

  echo tsprintf(
    "%s\n",
    pht(
      'Corrected account verification state for user "%s".',
      $user->getUsername()));
}
