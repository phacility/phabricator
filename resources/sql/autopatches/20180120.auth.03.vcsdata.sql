INSERT INTO {$NAMESPACE}_auth.auth_password
  (objectPHID, phid, passwordType, passwordHash, isRevoked,
    dateCreated, dateModified)
  SELECT userPHID, CONCAT('XVCS', id), 'vcs', passwordHash, 0,
      dateCreated, dateModified
    FROM {$NAMESPACE}_repository.repository_vcspassword;
