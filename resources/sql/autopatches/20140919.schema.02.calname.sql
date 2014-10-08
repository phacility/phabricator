ALTER TABLE {$NAMESPACE}_calendar.calendar_holiday
  CHANGE name name VARCHAR(64) NOT NULL COLLATE utf8_general_ci;
