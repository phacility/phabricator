<?php

$table = new PhabricatorCalendarEvent();
$conn = $table->establishConnection('w');

// Previously, "All Day" events were stored with a start and end date set to
// the earliest possible start and end seconds for the corresponding days. We
// now store all day events with their "date" epochs as UTC, separate from
// individual event times.
$zone_min = new DateTimeZone('Pacific/Midway');
$zone_max = new DateTimeZone('Pacific/Kiritimati');
$zone_utc = new DateTimeZone('UTC');

foreach (new LiskMigrationIterator($table) as $event) {
  // If this event has already migrated, skip it.
  if ($event->getAllDayDateFrom()) {
    continue;
  }

  $is_all_day = $event->getIsAllDay();

  $epoch_min = $event->getDateFrom();
  $epoch_max = $event->getDateTo();

  $date_min = new DateTime('@'.$epoch_min);
  $date_max = new DateTime('@'.$epoch_max);

  if ($is_all_day) {
    $date_min->setTimeZone($zone_min);
    $date_min->modify('+2 days');
    $date_max->setTimeZone($zone_max);
    $date_max->modify('-2 days');
  } else {
    $date_min->setTimeZone($zone_utc);
    $date_max->setTimeZone($zone_utc);
  }

  $string_min = $date_min->format('Y-m-d');
  $string_max = $date_max->format('Y-m-d 23:59:00');

  $allday_min = id(new DateTime($string_min, $zone_utc))->format('U');
  $allday_max = id(new DateTime($string_max, $zone_utc))->format('U');

  queryfx(
    $conn,
    'UPDATE %T SET allDayDateFrom = %d, allDayDateTo = %d
      WHERE id = %d',
    $table->getTableName(),
    $allday_min,
    $allday_max,
    $event->getID());
}
