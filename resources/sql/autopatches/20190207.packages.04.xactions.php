<?php

$table = new PhabricatorOwnersPackageTransaction();
$conn = $table->establishConnection('w');
$iterator = new LiskRawMigrationIterator($conn, $table->getTableName());

// Migrate "Auditing State" transactions for Owners Packages from old values
// (which were "0" or "1", as JSON integer literals, without quotes) to new
// values (which are JSON strings, with quotes).

foreach ($iterator as $row) {
  if ($row['transactionType'] !== 'owners.auditing') {
    continue;
  }

  $old_value = (int)$row['oldValue'];
  $new_value = (int)$row['newValue'];

  if (!$old_value) {
    $old_value = 'none';
  } else {
    $old_value = 'audit';
  }

  if (!$new_value) {
    $new_value = 'none';
  } else {
    $new_value = 'audit';
  }

  $old_value = phutil_json_encode($old_value);
  $new_value = phutil_json_encode($new_value);

  queryfx(
    $conn,
    'UPDATE %R SET oldValue = %s, newValue = %s WHERE id = %d',
    $table,
    $old_value,
    $new_value,
    $row['id']);
}
