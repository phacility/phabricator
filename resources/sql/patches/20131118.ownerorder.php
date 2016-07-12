<?php

$table = new ManiphestTask();
$conn_w = $table->establishConnection('w');

$user_table = new PhabricatorUser();
$user_conn = $user_table->establishConnection('r');

foreach (new LiskMigrationIterator($table) as $task) {
  $id = $task->getID();

  echo pht('Checking task %s...', "T{$id}")."\n";
  $owner_phid = $task->getOwnerPHID();

  if (!$owner_phid && !$task->getOwnerOrdering()) {
    // No owner and no ordering; we're all set.
    continue;
  }

  $owner_row = queryfx_one(
    $user_conn,
    'SELECT * FROM %T WHERE phid = %s',
    $user_table->getTableName(),
    $owner_phid);

  if ($owner_row) {
    $value = $owner_row['userName'];
  } else {
    $value = null;
  }

  if ($value !== $task->getOwnerOrdering()) {
    queryfx(
      $conn_w,
      'UPDATE %T SET ownerOrdering = %ns WHERE id = %d',
      $table->getTableName(),
      $value,
      $task->getID());
  }
}

echo pht('Done.')."\n";
