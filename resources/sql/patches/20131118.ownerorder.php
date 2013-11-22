<?php

$table = new ManiphestTask();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $task) {
  $id = $task->getID();

  echo "Checking task T{$id}...\n";
  $owner_phid = $task->getOwnerPHID();

  if (!$owner_phid && !$task->getOwnerOrdering()) {
    // No owner and no ordering; we're all set.
    continue;
  }

  $owner_handle = id(new PhabricatorHandleQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withPHIDs(array($owner_phid))
    ->executeOne();

  if ($owner_handle) {
    $value = $owner_handle->getName();
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

echo "Done.\n";
