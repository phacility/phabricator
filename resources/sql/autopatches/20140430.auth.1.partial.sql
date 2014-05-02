ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD isPartial BOOL NOT NULL DEFAULT 0;
