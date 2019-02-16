<?php

$account_table = new PhabricatorExternalAccount();
$account_conn = $account_table->establishConnection('w');
$table_name = $account_table->getTableName();

$config_table = new PhabricatorAuthProviderConfig();
$config_conn = $config_table->establishConnection('w');

foreach (new LiskRawMigrationIterator($account_conn, $table_name) as $row) {
  if (strlen($row['providerConfigPHID'])) {
    continue;
  }

  $config_row = queryfx_one(
    $config_conn,
    'SELECT phid
      FROM %R
      WHERE providerType = %s AND providerDomain = %s
      LIMIT 1',
    $config_table,
    $row['accountType'],
    $row['accountDomain']);
  if (!$config_row) {
    continue;
  }

  queryfx(
    $account_conn,
    'UPDATE %R
      SET providerConfigPHID = %s
      WHERE id = %d',
    $account_table,
    $config_row['phid'],
    $row['id']);
}
