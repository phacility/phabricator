ALTER TABLE phabricator_metamta.metamta_mailinglist
  ADD UNIQUE KEY (email);

ALTER TABLE phabricator_metamta.metamta_mailinglist
  ADD UNIQUE KEY (name);

