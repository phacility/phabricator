ALTER TABLE {$NAMESPACE}_repository.repository_refcursor
  ADD UNIQUE KEY `key_ref` (repositoryPHID, refType, refNameHash);
