ALTER TABLE {$NAMESPACE}_auth.auth_sshkey
  CHANGE keyIndex keyIndex BINARY(12) NOT NULL;
