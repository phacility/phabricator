ALTER TABLE {$NAMESPACE}_user.user
  ADD isDisabled bool NOT NULL;

ALTER TABLE {$NAMESPACE}_user.user
  ADD isAdmin bool NOT NULL;
