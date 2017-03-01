<?php

$table = new PhabricatorCalendarEvent();
$conn = $table->establishConnection('w');
$table_name = 'calendar_event';

foreach (new LiskRawMigrationIterator($conn, $table_name) as $row) {
  $parameters = phutil_json_decode($row['parameters']);
  if (isset($parameters['recurrenceRule'])) {
    // This event has already been migrated.
    continue;
  }

  if (!$row['isRecurring']) {
    continue;
  }

  $old_rule = $row['recurrenceFrequency'];
  if (!$old_rule) {
    continue;
  }

  try {
    $frequency = phutil_json_decode($old_rule);
    if ($frequency) {
      $frequency_rule = $frequency['rule'];
      $frequency_rule = phutil_utf8_strtoupper($frequency_rule);

      $rrule = id(new PhutilCalendarRecurrenceRule())
        ->setFrequency($frequency_rule);
    }
  } catch (Exception $ex) {
    continue;
  }

  $parameters['recurrenceRule'] = $rrule->toDictionary();

  queryfx(
    $conn,
    'UPDATE %T SET parameters = %s WHERE id = %d',
    $table_name,
    phutil_json_encode($parameters),
    $row['id']);
}
