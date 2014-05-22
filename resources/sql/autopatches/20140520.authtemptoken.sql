CREATE TABLE {$NAMESPACE}_auth.auth_temporarytoken (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  tokenType VARCHAR(64) NOT NULL COLLATE utf8_bin,
  tokenExpires INT UNSIGNED NOT NULL,
  tokenCode VARCHAR(64) NOT NULL COLLATE utf8_bin,

  UNIQUE KEY `key_token` (objectPHID, tokenType, tokenCode),
  KEY `key_expires` (tokenExpires)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
