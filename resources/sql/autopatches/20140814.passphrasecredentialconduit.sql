ALTER TABLE {$NAMESPACE}_passphrase.passphrase_credential
  ADD COLUMN allowConduit BOOL NOT NULL DEFAULT 0;
