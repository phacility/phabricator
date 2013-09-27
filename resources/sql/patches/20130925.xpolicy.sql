ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_differential.differential_revision
  SET viewPolicy = 'users' WHERE viewPolicy = '';

UPDATE {$NAMESPACE}_differential.differential_revision
  SET editPolicy = 'users' WHERE editPolicy = '';

ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD repositoryPHID VARCHAR(64) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD KEY (repositoryPHID);
