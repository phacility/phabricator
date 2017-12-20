<?php

$map = array(
  '0' => 'needs-review',
  '1' => 'needs-revision',
  '2' => 'accepted',
  '3' => 'published',
  '4' => 'abandoned',
  '5' => 'changes-planned',
);

$table = new DifferentialTransaction();
$conn = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $xaction) {
  $type = $xaction->getTransactionType();

  if (($type != 'differential:status') &&
      ($type != 'differential.revision.status')) {
    continue;
  }

  $old = $xaction->getOldValue();
  $new = $xaction->getNewValue();

  $old = idx($map, $old, $old);
  $new = idx($map, $new, $new);

  queryfx(
    $conn,
    'UPDATE %T SET transactionType = %s, oldValue = %s, newValue = %s
      WHERE id = %d',
    $table->getTableName(),
    'differential.revision.status',
    json_encode($old),
    json_encode($new),
    $xaction->getID());
}
