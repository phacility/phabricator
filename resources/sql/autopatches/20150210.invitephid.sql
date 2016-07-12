ALTER TABLE {$NAMESPACE}_user.user_authinvite
  ADD phid VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_user.user_authinvite
  ADD UNIQUE KEY `key_phid` (phid);
