ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD phid VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER id;
