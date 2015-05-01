<?php

echo "Adding mailkeys to events.\n";

$table = new PhabricatorCalendarEvent();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $event) {
  $id = $event->getID();

  echo "Populating event {$id}...\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}

echo "Done.\n";
