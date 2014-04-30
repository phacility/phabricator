CREATE TABLE {$NAMESPACE}_auth.auth_factorconfig (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  userPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  factorKey VARCHAR(64) NOT NULL COLLATE utf8_bin,
  factorName LONGTEXT NOT NULL COLLATE utf8_general_ci,
  factorSecret LONGTEXT NOT NULL COLLATE utf8_bin,
  properties LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_user` (userPHID),
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
