ALTER TABLE {$NAMESPACE}_user.phabricator_session
  DROP PRIMARY KEY;

ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD KEY `key_identity` (userPHID, type);
