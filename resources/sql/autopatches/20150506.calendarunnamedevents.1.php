<?php

echo pht('Retro-naming unnamed events.')."\n";

$table = new PhabricatorCalendarEvent();
$conn_w = $table->establishConnection('w');
$iterator = new LiskMigrationIterator($table);
foreach ($iterator as $event) {
  $id = $event->getID();

  if (strlen($event->getName()) == 0) {
    echo pht('Renaming event %d...', $id)."\n";
    $viewer = PhabricatorUser::getOmnipotentUser();

    // NOTE: This uses PeopleQuery directly, instead of HandleQuery, to avoid
    // performing cache fills as a side effect; the caches were added by a
    // later patch. See T8209.
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($event->getUserPHID()))
      ->executeOne();

    if ($user) {
      $new_name = $user->getUsername();
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

echo pht('Done.')."\n";
