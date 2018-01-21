INSERT INTO {$NAMESPACE}_auth.auth_password
  (objectPHID, phid, passwordType, passwordHash, passwordSalt, isRevoked,
    dateCreated, dateModified)
  SELECT phid, CONCAT('XACCOUNT', id), 'account', passwordHash, passwordSalt, 0,
      dateCreated, dateModified
    FROM {$NAMESPACE}_user.user
    WHERE passwordHash != '';
