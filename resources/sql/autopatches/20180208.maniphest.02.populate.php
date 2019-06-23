<?php

$table = new ManiphestTask();
$conn = $table->establishConnection('w');
$viewer = PhabricatorUser::getOmnipotentUser();

foreach (new LiskMigrationIterator($table) as $task) {
  if ($task->getClosedEpoch()) {
    // Task already has a closed date.
    continue;
  }

  $status = $task->getStatus();
  if (!ManiphestTaskStatus::isClosedStatus($status)) {
    // Task isn't closed.
    continue;
  }

  // Look through the transactions from newest to oldest until we find one
  // where the task was closed. A merge also counts as a close, even though
  // it doesn't currently produce a separate transaction.

  $type_status = ManiphestTaskStatusTransaction::TRANSACTIONTYPE;
  $type_merge = ManiphestTaskMergedIntoTransaction::TRANSACTIONTYPE;

  $xactions = id(new ManiphestTransactionQuery())
    ->setViewer($viewer)
    ->withObjectPHIDs(array($task->getPHID()))
    ->needHandles(false)
    ->withTransactionTypes(
      array(
        $type_merge,
        $type_status,
      ))
    ->execute();
  foreach ($xactions as $xaction) {
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $type = $xaction->getTransactionType();

    // If this is a status change, but is not a close, don't use it.
    // (We always use merges, even though it's possible to merge a task which
    // was previously closed: we can't tell when this happens very easily.)
    if ($type === $type_status) {
      if (!ManiphestTaskStatus::isClosedStatus($new)) {
        continue;
      }

      if ($old && ManiphestTaskStatus::isClosedStatus($old)) {
        continue;
      }
    }

    queryfx(
      $conn,
      'UPDATE %T SET closedEpoch = %d, closerPHID = %ns
        WHERE id = %d',
      $table->getTableName(),
      $xaction->getDateCreated(),
      $xaction->getAuthorPHID(),
      $task->getID());

    break;
  }
}
