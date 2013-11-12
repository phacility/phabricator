ALTER TABLE {$NAMESPACE}_user.user
  ADD isEmailVerified INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_user.user
  ADD isApproved INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_user.user
  ADD KEY `key_approved` (isApproved);

UPDATE {$NAMESPACE}_user.user SET isApproved = 1;
