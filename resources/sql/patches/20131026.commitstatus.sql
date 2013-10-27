ALTER TABLE {$NAMESPACE}_repository.repository_commit
  ADD COLUMN importStatus INT UNSIGNED NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_repository.repository_commit
  SET importStatus = 15;

ALTER TABLE {$NAMESPACE}_repository.repository_commit
  ADD KEY (repositoryID, importStatus);
