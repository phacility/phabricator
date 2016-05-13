ALTER TABLE {$NAMESPACE}_repository.repository
  ADD UNIQUE KEY `key_local` (localPath);
