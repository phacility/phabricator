ALTER TABLE {$NAMESPACE}_auth.auth_providerconfig
  ADD `shouldTrustEmails` tinyint(1) NOT NULL DEFAULT 0 AFTER shouldAllowUnlink;
