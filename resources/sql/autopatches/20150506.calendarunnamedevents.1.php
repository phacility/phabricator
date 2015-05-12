<?php

echo "Retro-naming unnamed events.\n";

$table = new PhabricatorCalendarEvent();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $event) {
  $id = $event->getID();

  if (strlen($event->getName()) == 0) {
    echo "Renaming event {$id}...\n";
    $viewer = PhabricatorUser::getOmnipotentUser();
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($event->getUserPHID()))
      ->executeOne();
    if ($handle->isComplete()) {
      $new_name = $handle->getName();
    } else {
      $new_name = pht('Unnamed Event');
    }

    queryfx(
      $conn_w,
      'UPDATE %T SET name = %s WHERE id = %d',
      $table->getTableName(),
      $new_name,
      $id);
  }
}

echo "Done.\n";
