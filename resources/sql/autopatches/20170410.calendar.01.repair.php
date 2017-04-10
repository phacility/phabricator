<?php

// See T12488. Some events survived "20161004.cal.01.noepoch.php" without
// having "utcInstanceEpoch" computed, which breaks ICS export. This appears
// to be the result of some bug which has been fixed in the meantime, so just
// redo this part of the migration.

$table = new PhabricatorCalendarEvent();
$conn = $table->establishConnection('w');
$table_name = $table->getTableName();

$viewer = PhabricatorUser::getOmnipotentUser();
$all_events = id(new PhabricatorCalendarEventQuery())
  ->setViewer($viewer)
  ->execute();
foreach ($all_events as $event) {
  $id = $event->getID();

  if (!$event->getInstanceOfEventPHID()) {
    // Not a child event, so no instance epoch.
    continue;
  }

  if ($event->getUTCInstanceEpoch()) {
    // Already has an instance epoch.
    continue;
  }

  try {
    $event->updateUTCEpochs();
  } catch (Exception $ex) {
    phlog($ex);
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET utcInstanceEpoch = %nd WHERE id = %d',
    $table_name,
    $event->getUTCInstanceEpoch(),
    $id);
}
