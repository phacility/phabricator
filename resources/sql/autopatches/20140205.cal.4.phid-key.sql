ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD UNIQUE KEY `key_phid` (phid);
