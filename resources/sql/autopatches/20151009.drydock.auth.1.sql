CREATE TABLE {$NAMESPACE}_drydock.drydock_authorization (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  blueprintPHID VARBINARY(64) NOT NULL,
  blueprintAuthorizationState VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT},
  objectPHID VARBINARY(64) NOT NULL,
  objectAuthorizationState VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT},
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_unique` (objectPHID, blueprintPHID),
  KEY `key_blueprint` (blueprintPHID, blueprintAuthorizationState),
  KEY `key_object` (objectPHID, objectAuthorizationState)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
