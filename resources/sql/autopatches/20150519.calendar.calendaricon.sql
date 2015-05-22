ALTER TABLE {$NAMESPACE}_calendar.calendar_event
  ADD COLUMN icon VARCHAR(32) COLLATE {$COLLATE_TEXT} NOT NULL;

UPDATE {$NAMESPACE}_calendar.calendar_event
  SET icon = "fa-calendar" WHERE icon = "";
