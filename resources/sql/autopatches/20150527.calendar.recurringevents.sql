ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD isRecurring BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD recurrenceFrequency LONGTEXT COLLATE {$COLLATE_TEXT} NOT NULL;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD recurrenceEndDate INT UNSIGNED;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD instanceOfEventPHID varbinary(64);

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
    ADD sequenceIndex INT UNSIGNED;

UPDATE {$NAMESPACE}_calendar.calendar_event
    SET recurrenceFrequency = '[]' WHERE recurrenceFrequency = '';
