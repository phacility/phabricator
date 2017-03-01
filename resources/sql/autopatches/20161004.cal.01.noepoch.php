<?php

$table = new PhabricatorCalendarEvent();
$conn = $table->establishConnection('w');
$table_name = 'calendar_event';

// Long ago, "All Day" events were stored with a start and end date set to
// the earliest possible start and end seconds for the corresponding days. We
// then moved to store all day events with their "date" epochs as UTC, separate
// from individual event times. Both systems were later replaced with use of
// CalendarDateTime.
$zone_min = new DateTimeZone('Pacific/Midway');
$zone_max = new DateTimeZone('Pacific/Kiritimati');
$zone_utc = new DateTimeZone('UTC');

foreach (new LiskRawMigrationIterator($conn, $table_name) as $row) {
  $parameters = phutil_json_decode($row['parameters']);
  if (isset($parameters['startDateTime'])) {
    // This event has already been migrated.
    continue;
  }

  $is_all_day = $row['isAllDay'];

  if (empty($row['allDayDateFrom'])) {
    // No "allDayDateFrom" means this is an old event which was never migrated
    // by the earlier "20160715.event.03.allday.php" migration. The dateFrom
    // and dateTo will be minimum and maximum earthly seconds for the event. We
    // convert them to UTC if they were in extreme timezones.
    $epoch_min = $row['dateFrom'];
    $epoch_max = $row['dateTo'];

    if ($is_all_day) {
      $date_min = new DateTime('@'.$epoch_min);
      $date_max = new DateTime('@'.$epoch_max);

      $date_min->setTimeZone($zone_min);
      $date_min->modify('+2 days');
      $date_max->setTimeZone($zone_max);
      $date_max->modify('-2 days');

      $string_min = $date_min->format('Y-m-d');
      $string_max = $date_max->format('Y-m-d 23:59:00');

      $utc_min = id(new DateTime($string_min, $zone_utc))->format('U');
      $utc_max = id(new DateTime($string_max, $zone_utc))->format('U');
    } else {
      $utc_min = $epoch_min;
      $utc_max = $epoch_max;
    }
  } else {
    // This is an event which was migrated already. We can pick the correct
    // epoch timestamps based on the "isAllDay" flag.
    if ($is_all_day) {
      $utc_min = $row['allDayDateFrom'];
      $utc_max = $row['allDayDateTo'];
    } else {
      $utc_min = $row['dateFrom'];
      $utc_max = $row['dateTo'];
    }
  }

  $utc_until = $row['recurrenceEndDate'];

  $start_datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch($utc_min);
  if ($is_all_day) {
    $start_datetime->setIsAllDay(true);
  }

  $end_datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch($utc_max);
  if ($is_all_day) {
    $end_datetime->setIsAllDay(true);
  }

  if ($utc_until) {
    $until_datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch($utc_until);
  } else {
    $until_datetime = null;
  }

  $parameters['startDateTime'] = $start_datetime->toDictionary();
  $parameters['endDateTime'] = $end_datetime->toDictionary();
  if ($until_datetime) {
    $parameters['untilDateTime'] = $until_datetime->toDictionary();
  }

  queryfx(
    $conn,
    'UPDATE %T SET parameters = %s WHERE id = %d',
    $table_name,
    phutil_json_encode($parameters),
    $row['id']);
}

// Generate UTC epochs for all events. We can't readily do this one at a
// time because instance UTC epochs rely on having the parent event.
$viewer = PhabricatorUser::getOmnipotentUser();

$all_events = id(new PhabricatorCalendarEventQuery())
  ->setViewer($viewer)
  ->execute();
foreach ($all_events as $event) {
  if ($event->getUTCInitialEpoch()) {
    // Already migrated.
    continue;
  }

  try {
    $event->updateUTCEpochs();
  } catch (Exception $ex) {
    continue;
  }

  queryfx(
    $conn,
    'UPDATE %T SET
      utcInitialEpoch = %d,
      utcUntilEpoch = %nd,
      utcInstanceEpoch = %nd WHERE id = %d',
    $table_name,
    $event->getUTCInitialEpoch(),
    $event->getUTCUntilEpoch(),
    $event->getUTCInstanceEpoch(),
    $event->getID());
}
