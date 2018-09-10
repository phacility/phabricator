<?php

$table = new PhabricatorAuditTransaction();
$conn = $table->establishConnection('w');

$status_map = array(
  0 => 'none',
  1 => 'needs-audit',
  2 => 'concern-raised',
  3 => 'partially-audited',
  4 => 'audited',
  5 => 'needs-verification',
);

$state_type = DiffusionCommitStateTransaction::TRANSACTIONTYPE;

foreach (new LiskMigrationIterator($table) as $xaction) {
  if ($xaction->getTransactionType() !== $state_type) {
    continue;
  }

  $old_value = $xaction->getOldValue();
  $new_value = $xaction->getNewValue();

  $any_change = false;

  if (isset($status_map[$old_value])) {
    $old_value = $status_map[$old_value];
    $any_change = true;
  }

  if (isset($status_map[$new_value])) {
    $new_value = $status_map[$new_value];
    $any_change = true;
  }

  if (!$any_change) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET oldValue = %s, newValue = %s WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($old_value),
    phutil_json_encode($new_value),
    $xaction->getID());
}
