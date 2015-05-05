ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  ADD imagePHIDs LONGTEXT COLLATE {$COLLATE_TEXT} NOT NULL AFTER title;

UPDATE {$NAMESPACE}_conpherence.conpherence_thread
  SET imagePHIDS = '[]';
