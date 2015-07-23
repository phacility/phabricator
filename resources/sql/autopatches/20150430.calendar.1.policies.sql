ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD viewPolicy varbinary(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD editPolicy varbinary(64) NOT NULL;

UPDATE {$NAMESPACE}_calendar.calendar_event
  SET viewPolicy = 'users' WHERE viewPolicy = '';

UPDATE {$NAMESPACE}_calendar.calendar_event
  SET editPolicy = userPHID;
