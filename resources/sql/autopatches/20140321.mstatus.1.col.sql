ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  CHANGE status status VARCHAR(12) NOT NULL COLLATE latin1_bin;
