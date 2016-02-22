<?php

$table = new AlmanacProperty();
$conn_w = $table->establishConnection('w');

// We're going to JSON-encode the value in each row: previously rows stored
// plain strings, but now they store JSON, so we need to update them.

foreach (new LiskMigrationIterator($table) as $property) {
  $key = $property->getFieldName();

  $current_row = queryfx_one(
    $conn_w,
    'SELECT fieldValue FROM %T WHERE id = %d',
    $table->getTableName(),
    $property->getID());

  if (!$current_row) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET fieldValue = %s WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($current_row['fieldValue']),
    $property->getID());
}
