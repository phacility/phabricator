ALTER TABLE phabricator_repository.repository_commit
  ADD mailKey VARCHAR(20) NOT NULL;

ALTER TABLE phabricator_repository.repository_commit
  ADD authorPHID VARCHAR(64) BINARY;

ALTER TABLE phabricator_repository.repository_commit
  ADD auditStatus INT UNSIGNED NOT NULL;

ALTER TABLE phabricator_repository.repository_commit
  ADD KEY (authorPHID, auditStatus, epoch);