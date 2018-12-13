ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD UNIQUE KEY `key_phid` (phid);
