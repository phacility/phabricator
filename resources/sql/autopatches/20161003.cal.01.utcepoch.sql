ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD utcInitialEpoch INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD utcUntilEpoch INT UNSIGNED;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD utcInstanceEpoch INT UNSIGNED;
