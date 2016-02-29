<?php

// Previously, Almanac services stored a "serviceClass". Migrate these to
// new "serviceType" values.

$table = new AlmanacService();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $service) {

  $new_type = null;
  try {
    $old_type = $service->getServiceType();
    $object = newv($old_type, array());
    $new_type = $object->getServiceTypeConstant();
  } catch (Exception $ex) {
    continue;
  }

  if (!$new_type) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET serviceType = %s WHERE id = %d',
    $table->getTableName(),
    $new_type,
    $service->getID());
}
