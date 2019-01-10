ALTER TABLE {$NAMESPACE}_user.phabricator_session
  CHANGE sessionKey sessionKey VARBINARY(64) NOT NULL;
