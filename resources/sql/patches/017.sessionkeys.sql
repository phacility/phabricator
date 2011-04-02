ALTER TABLE phabricator_user.user ADD UNIQUE KEY (phid);
ALTER TABLE phabricator_user.phabricator_session ADD UNIQUE KEY (sessionKey);
