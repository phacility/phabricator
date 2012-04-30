ALTER TABLE {$NAMESPACE}_differential.differential_comment
  ADD metadata LONGBLOB NOT NULL;

UPDATE {$NAMESPACE}_differential.differential_comment
  SET metadata = '{}' WHERE metadata = '';

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_transaction
  ADD metadata LONGBLOB NOT NULL;

UPDATE {$NAMESPACE}_maniphest.maniphest_transaction
  SET metadata = '{}' WHERE metadata = '';
