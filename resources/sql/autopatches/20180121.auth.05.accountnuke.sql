ALTER TABLE {$NAMESPACE}_user.user
  DROP passwordSalt;

ALTER TABLE {$NAMESPACE}_user.user
  DROP passwordHash;
