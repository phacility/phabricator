<?php

// Previously, MFA factors for individual users were bound to raw factor types.
// The only factor type ever implemented in the upstream was "totp".

// Going forward, individual factors are bound to a provider instead. This
// allows factor types to have some configuration, like API keys for
// service-based MFA. It also allows installs to select which types of factors
// they want users to be able to set up.

// Migrate all existing TOTP factors to the first available TOTP provider,
// creating one if none exists. This migration is a little bit messy, but
// gives us a clean slate going forward with no "builtin" providers.

$table = new PhabricatorAuthFactorConfig();
$conn = $table->establishConnection('w');

$provider_table = new PhabricatorAuthFactorProvider();
$provider_phid = null;
$iterator = new LiskRawMigrationIterator($conn, $table->getTableName());
$totp_key = 'totp';
foreach ($iterator as $row) {

  // This wasn't a TOTP factor, so skip it.
  if ($row['factorKey'] !== $totp_key) {
    continue;
  }

  // This factor already has an associated provider.
  if (strlen($row['factorProviderPHID'])) {
    continue;
  }

  // Find (or create) a suitable TOTP provider. Note that we can't "save()"
  // an object or this migration will break if the object ever gets new
  // columns; just INSERT the raw fields instead.

  if ($provider_phid === null) {
    $provider_row = queryfx_one(
      $conn,
      'SELECT phid FROM %R WHERE providerFactorKey = %s LIMIT 1',
      $provider_table,
      $totp_key);

    if ($provider_row) {
      $provider_phid = $provider_row['phid'];
    } else {
      $provider_phid = $provider_table->generatePHID();
      queryfx(
        $conn,
        'INSERT INTO %R
          (phid, providerFactorKey, name, status, properties,
            dateCreated, dateModified)
          VALUES (%s, %s, %s, %s, %s, %d, %d)',
        $provider_table,
        $provider_phid,
        $totp_key,
        '',
        'active',
        '{}',
        PhabricatorTime::getNow(),
        PhabricatorTime::getNow());
    }
  }

  queryfx(
    $conn,
    'UPDATE %R SET factorProviderPHID = %s WHERE id = %d',
    $table,
    $provider_phid,
    $row['id']);
}
