ALTER TABLE {$NAMESPACE}_auth.auth_temporarytoken
  ADD properties LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
