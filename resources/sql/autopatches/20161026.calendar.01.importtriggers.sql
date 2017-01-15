ALTER TABLE {$NAMESPACE}_calendar.calendar_import
  ADD triggerPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_calendar.calendar_import
  ADD triggerFrequency VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT};

UPDATE {$NAMESPACE}_calendar.calendar_import
  SET triggerFrequency = 'once' WHERE triggerFrequency = '';
