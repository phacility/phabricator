ALTER TABLE {$NAMESPACE}_auth.auth_sshkey
  ADD UNIQUE KEY `key_activeunique` (keyIndex, isActive);
