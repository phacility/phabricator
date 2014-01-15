ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD sessionExpires INT UNSIGNED NOT NULL;

UPDATE {$NAMESPACE}_user.phabricator_session
  SET sessionExpires = UNIX_TIMESTAMP() + (60 * 60 * 24 * 30);

ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD KEY `key_expires` (sessionExpires);
