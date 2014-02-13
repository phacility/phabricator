<?php

$table = new PhabricatorCalendarEvent();
$conn_w = $table->establishConnection('w');

echo "Assigning PHIDs to events...\n";
foreach (new LiskMigrationIterator($table) as $event) {
  $id = $event->getID();

  echo "Updating event {$id}...\n";
  if ($event->getPHID()) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET phid = %s WHERE id = %d',
    $table->getTableName(),
    $table->generatePHID(),
    $id);
}
echo "Done.\n";
