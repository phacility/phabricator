ALTER TABLE {$NAMESPACE}_metamta.metamta_mailinglist
  ADD UNIQUE KEY (email);

ALTER TABLE {$NAMESPACE}_metamta.metamta_mailinglist
  ADD UNIQUE KEY (name);
