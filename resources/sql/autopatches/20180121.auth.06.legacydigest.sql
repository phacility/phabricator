ALTER TABLE {$NAMESPACE}_auth.auth_password
  ADD legacyDigestFormat VARCHAR(32) COLLATE {$COLLATE_TEXT};
