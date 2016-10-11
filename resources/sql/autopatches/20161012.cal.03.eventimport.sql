ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD importAuthorPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD importSourcePHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD importUIDIndex BINARY(12);

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD importUID LONGTEXT COLLATE {$COLLATE_TEXT};
