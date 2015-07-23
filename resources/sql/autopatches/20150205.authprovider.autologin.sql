ALTER TABLE {$NAMESPACE}_auth.auth_providerconfig
  ADD shouldAutoLogin TINYINT(1) NOT NULL DEFAULT '0';
