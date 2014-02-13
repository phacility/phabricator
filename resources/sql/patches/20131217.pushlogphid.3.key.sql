ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  ADD UNIQUE KEY `key_phid` (phid);
