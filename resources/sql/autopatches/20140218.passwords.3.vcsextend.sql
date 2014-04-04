ALTER TABLE {$NAMESPACE}_repository.repository_vcspassword
  CHANGE passwordHash passwordHash VARCHAR(128) COLLATE utf8_bin NOT NULL;
