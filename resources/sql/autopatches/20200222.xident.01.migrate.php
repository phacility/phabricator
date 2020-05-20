<?php

$account_table = new PhabricatorExternalAccount();
$identifier_table = new PhabricatorExternalAccountIdentifier();

$conn = $account_table->establishConnection('w');
$table_name = $account_table->getTableName();

$iterator = new LiskRawMigrationIterator($conn, $table_name);
foreach ($iterator as $account_row) {
  // We don't need to migrate "accountID" values for "password" accounts,
  // since these were dummy values in the first place and are no longer
  // read or written after D21014. (There would be no harm in writing these
  // rows, but it's easy to skip them.)

  if ($account_row['accountType'] === 'password') {
    continue;
  }

  $account_id = $account_row['accountID'];
  if (!strlen($account_id)) {
    continue;
  }

  queryfx(
    $conn,
    'INSERT IGNORE INTO %R (
        phid, externalAccountPHID, providerConfigPHID,
        identifierHash, identifierRaw,
        dateCreated, dateModified)
      VALUES (%s, %s, %s, %s, %s, %d, %d)',
    $identifier_table,
    $identifier_table->generatePHID(),
    $account_row['phid'],
    $account_row['providerConfigPHID'],
    PhabricatorHash::digestForIndex($account_id),
    $account_id,
    $account_row['dateCreated'],
    $account_row['dateModified']);
}
