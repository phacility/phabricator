ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  DROP imagePHID,
  ADD imagePHIDs LONGTEXT COLLATE utf8_bin NOT NULL AFTER title;

UPDATE {$NAMESPACE}_conpherence.conpherence_thread
  SET imagePHIDs = '{}' WHERE imagePHIDs = '';
