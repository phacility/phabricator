ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD viewPolicy VARBINARY(64) NOT NULL;

UPDATE {$NAMESPACE}_differential.differential_diff
  SET viewPolicy = 'users' WHERE viewPolicy = '';
