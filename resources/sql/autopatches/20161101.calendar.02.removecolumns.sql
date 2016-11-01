ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  DROP allDayDateFrom;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  DROP allDayDateTo;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  DROP dateFrom;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  DROP dateTo;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  DROP recurrenceEndDate;

ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  DROP recurrenceFrequency;
