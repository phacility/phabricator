CREATE TABLE {$NAMESPACE}_user.user_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actorPHID varchar(64) BINARY,
  key(actorPHID, dateCreated),
  userPHID varchar(64) BINARY NOT NULL,
  key(userPHID, dateCreated),
  action varchar(64) NOT NULL,
  key(action, dateCreated),
  oldValue LONGBLOB NOT NULL,
  newValue LONGBLOB NOT NULL,
  details LONGBLOB NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  key(dateCreated)
);

ALTER TABLE {$NAMESPACE}_user.user_log
  ADD remoteAddr varchar(16) NOT NULL;

ALTER TABLE {$NAMESPACE}_user.user_log
  ADD KEY (remoteAddr, dateCreated);

ALTER TABLE {$NAMESPACE}_user.user_log
  ADD session varchar(40);

ALTER TABLE {$NAMESPACE}_user.user_log
  ADD KEY (session, dateCreated);
