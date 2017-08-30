TRUNCATE TABLE {$NAMESPACE}_maniphest.maniphest_task_ffield;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task_ffield
  ADD UNIQUE KEY `key_documentfield` (documentID, fieldKey);
