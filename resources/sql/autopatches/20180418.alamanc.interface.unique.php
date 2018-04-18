<?php

$interface_table = new AlmanacInterface();
$binding_table = new AlmanacBinding();
$interface_conn = $interface_table->establishConnection('w');

queryfx(
  $interface_conn,
  'LOCK TABLES %T WRITE, %T WRITE',
  $interface_table->getTableName(),
  $binding_table->getTableName());

$seen = array();
foreach (new LiskMigrationIterator($interface_table) as $interface) {
  $device = $interface->getDevicePHID();
  $network = $interface->getNetworkPHID();
  $address = $interface->getAddress();
  $port = $interface->getPort();
  $key = "{$device}/{$network}/{$address}/{$port}";

  // If this is the first copy of this row we've seen, mark it as seen and
  // move on.
  if (empty($seen[$key])) {
    $seen[$key] = $interface->getID();
    continue;
  }

  $survivor = queryfx_one(
    $interface_conn,
    'SELECT * FROM %T WHERE id = %d',
    $interface_table->getTableName(),
    $seen[$key]);

  $bindings = queryfx_all(
    $interface_conn,
    'SELECT * FROM %T WHERE interfacePHID = %s',
    $binding_table->getTableName(),
    $interface->getPHID());

  // Repoint bindings to the survivor.
  foreach ($bindings as $binding) {
    // Check if there's already a binding to the survivor.
    $existing = queryfx_one(
      $interface_conn,
      'SELECT * FROM %T WHERE interfacePHID = %s and devicePHID = %s and '.
      'servicePHID = %s',
      $binding_table->getTableName(),
      $survivor['phid'],
      $binding['devicePHID'],
      $binding['servicePHID']);

    if (!$existing) {
      // Reattach this binding to the survivor.
      queryfx(
        $interface_conn,
        'UPDATE %T SET interfacePHID = %s WHERE id = %d',
        $binding_table->getTableName(),
        $survivor['phid'],
        $binding['id']);
    } else {
      // Binding to survivor already exists. Remove this now-redundant binding.
      queryfx(
        $interface_conn,
        'DELETE FROM %T WHERE id = %d',
        $binding_table->getTableName(),
        $binding['id']);
    }
  }

  queryfx(
    $interface_conn,
    'DELETE FROM %T WHERE id = %d',
    $interface_table->getTableName(),
    $interface->getID());
}

queryfx(
  $interface_conn,
  'ALTER TABLE %T ADD UNIQUE KEY `key_unique` '.
  '(devicePHID, networkPHID, address, port)',
  $interface_table->getTableName());

queryfx(
  $interface_conn,
  'UNLOCK TABLES');
