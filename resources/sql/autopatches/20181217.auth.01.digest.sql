ALTER TABLE {$NAMESPACE}_auth.auth_challenge
  ADD responseDigest VARCHAR(255) COLLATE {$COLLATE_TEXT};
