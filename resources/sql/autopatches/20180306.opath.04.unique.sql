ALTER TABLE {$NAMESPACE}_owners.owners_path
  ADD UNIQUE KEY `key_path` (packageID, repositoryPHID, pathIndex);
