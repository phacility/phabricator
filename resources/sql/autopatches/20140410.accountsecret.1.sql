ALTER TABLE {$NAMESPACE}_user.user
  ADD accountSecret CHAR(64) NOT NULL COLLATE latin1_bin;
