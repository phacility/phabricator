<?php

$table = new PhabricatorCalendarEvent();
$conn_w = $table->establishConnection('w');

echo pht('Assigning PHIDs to events...')."\n";
foreach (new LiskMigrationIterator($table) as $event) {
  $id = $event->getID();

  echo pht('Updating event %d...', $id)."\n";
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
echo pht('Done.')."\n";
