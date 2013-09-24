ALTER TABLE {$NAMESPACE}_repository.repository_summary
  ADD KEY `key_epoch` (epoch);

ALTER TABLE {$NAMESPACE}_repository.repository
  ADD KEY `key_name` (name);

ALTER TABLE {$NAMESPACE}_repository.repository
  ADD KEY `key_vcs` (versionControlSystem);

ALTER TABLE {$NAMESPACE}_repository.repository
  ADD viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_repository.repository
  ADD editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_repository.repository
  SET viewPolicy = 'users' WHERE viewPolicy = '';

UPDATE {$NAMESPACE}_repository.repository
  SET editPolicy = 'admin' WHERE editPolicy = '';
