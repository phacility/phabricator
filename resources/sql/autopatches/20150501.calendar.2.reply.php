<?php

echo pht('Adding %s to events.', 'mailkeys')."\n";

$table = new PhabricatorCalendarEvent();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $event) {
  $id = $event->getID();

  echo pht('Populating event %d...', $id)."\n";

  queryfx(
    $conn_w,
    'UPDATE %T SET mailKey = %s WHERE id = %d',
    $table->getTableName(),
    Filesystem::readRandomCharacters(20),
    $id);
}

echo pht('Done.')."\n";
