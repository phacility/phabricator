ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  ADD viewPolicy VARBINARY(64) NOT NULL AFTER recentParticipantPHIDs;

UPDATE {$NAMESPACE}_conpherence.conpherence_thread
  SET viewPolicy = 'users' WHERE viewPolicy = '';

ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  ADD editPolicy VARBINARY(64) NOT NULL AFTER viewPolicy;

UPDATE {$NAMESPACE}_conpherence.conpherence_thread
  SET editPolicy = 'users' WHERE editPolicy = '';

ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  ADD joinPolicy VARBINARY(64) NOT NULL AFTER editPolicy;

UPDATE {$NAMESPACE}_conpherence.conpherence_thread
  SET joinPolicy = 'users' WHERE joinPolicy = '';
