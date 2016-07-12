<?php

echo pht('Migrating user emails...')."\n";

$table  = new PhabricatorUser();
$table->openTransaction();
$conn   = $table->establishConnection('w');

$emails = queryfx_all(
  $conn,
  'SELECT phid, email FROM %T LOCK IN SHARE MODE',
  $table->getTableName());
$emails = ipull($emails, 'email', 'phid');

$etable = new PhabricatorUserEmail();

foreach ($emails as $phid => $email) {

  // NOTE: Grandfather all existing email in as primary / verified. We generate
  // verification codes because they are used for password resets, etc.

  echo pht("Migrating '%s'...", $phid)."\n";
  queryfx(
    $conn,
    'INSERT INTO %T (userPHID, address, verificationCode, isVerified, isPrimary)
      VALUES (%s, %s, %s, 1, 1)',
    $etable->getTableName(),
    $phid,
    $email,
    Filesystem::readRandomCharacters(24));
}

$table->saveTransaction();
echo pht('Done.')."\n";
