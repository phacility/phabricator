CREATE TABLE {$NAMESPACE}_conduit.conduit_token (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARBINARY(64) NOT NULL,
  tokenType VARCHAR(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  token VARCHAR(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  expires INT UNSIGNED,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_object` (objectPHID, tokenType),
  UNIQUE KEY `key_token` (token),
  KEY `key_expires` (expires)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
