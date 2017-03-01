ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD seriesParentPHID VARBINARY(64);

UPDATE {$NAMESPACE}_calendar.calendar_event
  SET seriesParentPHID = instanceOfEventPHID;
