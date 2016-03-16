ALTER TABLE {$NAMESPACE}_auth.auth_temporarytoken
  CHANGE objectPHID tokenResource VARBINARY(64) NOT NULL;
