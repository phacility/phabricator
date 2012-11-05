<?php

echo "Migrating user emails...\n";

$table  = new PhabricatorUser();
$conn   = $table->establishConnection('r');

$emails = queryfx_all(
  $conn,
  'SELECT phid, email FROM %T',
  $table->getTableName());
$emails = ipull($emails, 'email', 'phid');

$etable = new PhabricatorUserEmail();
$econn  = $etable->establishConnection('w');

foreach ($emails as $phid => $email) {

  // NOTE: Grandfather all existing email in as primary / verified. We generate
  // verification codes because they are used for password resets, etc.

  echo "Migrating '{$phid}'...\n";
  queryfx(
    $econn,
    'INSERT INTO %T (userPHID, address, verificationCode, isVerified, isPrimary)
      VALUES (%s, %s, %s, 1, 1)',
    $etable->getTableName(),
    $phid,
    $email,
    Filesystem::readRandomCharacters(24));
}

echo "Done.\n";
