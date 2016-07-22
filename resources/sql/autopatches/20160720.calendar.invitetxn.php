<?php

$table = new PhabricatorCalendarEventTransaction();
$conn_w = $table->establishConnection('w');

echo pht(
  "Restructuring calendar invite transactions...\n");

foreach (new LiskMigrationIterator($table) as $txn) {
  $type = PhabricatorCalendarEventInviteTransaction::TRANSACTIONTYPE;
  if ($txn->getTransactionType() != $type) {
    continue;
  }

  $old_value = array_keys($txn->getOldValue());

  $orig_new = $txn->getNewValue();
  $status_uninvited = 'uninvited';
  foreach ($orig_new as $key => $status) {
    if ($status == $status_uninvited) {
      unset($orig_new[$key]);
    }
  }
  $new_value = array_keys($orig_new);

  queryfx(
    $conn_w,
    'UPDATE %T SET '.
      'oldValue = %s, newValue = %s'.
    'WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($old_value),
    phutil_json_encode($new_value),
    $txn->getID());
}

echo pht('Done.')."\n";
