ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD parameters LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};

UPDATE {$NAMESPACE}_calendar.calendar_event
  SET parameters = '{}' WHERE parameters = '';
