ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD phid VARBINARY(64) NOT NULL;
