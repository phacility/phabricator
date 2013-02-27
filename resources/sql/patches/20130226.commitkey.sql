ALTER TABLE {$NAMESPACE}_repository.repository_commit
  DROP KEY `repositoryID`;

ALTER TABLE {$NAMESPACE}_repository.repository_commit
  ADD UNIQUE KEY `key_commit_identity` (commitIdentifier, repositoryID);
