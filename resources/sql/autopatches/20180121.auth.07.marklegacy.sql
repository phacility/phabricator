UPDATE {$NAMESPACE}_auth.auth_password
  SET legacyDigestFormat = 'v1'
  WHERE passwordType IN ('vcs', 'account')
  AND legacyDigestFormat IS NULL;
