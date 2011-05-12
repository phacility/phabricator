ALTER TABLE phabricator_user.user
  ADD isDisabled bool NOT NULL;

ALTER TABLE phabricator_user.user
  ADD isAdmin bool NOT NULL;
