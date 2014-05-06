ALTER TABLE {$NAMESPACE}_passphrase.passphrase_credential
  ADD COLUMN isLocked BOOL NOT NULL;
