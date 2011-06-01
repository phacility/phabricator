ALTER TABLE phabricator_differential.differential_comment
  ADD metadata LONGBLOB NOT NULL;

UPDATE phabricator_differential.differential_comment
  SET metadata = '{}' WHERE metadata = '';

ALTER TABLE phabricator_maniphest.maniphest_transaction
  ADD metadata LONGBLOB NOT NULL;

UPDATE phabricator_maniphest.maniphest_transaction
  SET metadata = '{}' WHERE metadata = '';
