ALTER TABLE phabricator_metamta.metamta_mail
  ADD KEY (status, nextRetry);

ALTER TABLE phabricator_metamta.metamta_mail
  ADD KEY (relatedPHID);
