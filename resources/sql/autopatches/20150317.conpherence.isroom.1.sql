ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  ADD isRoom BOOL NOT NULL DEFAULT 0 AFTER title;
