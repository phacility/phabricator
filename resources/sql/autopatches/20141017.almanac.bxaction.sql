CREATE TABLE {$NAMESPACE}_almanac.almanac_bindingtransaction (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) COLLATE utf8_bin NOT NULL,
  authorPHID VARCHAR(64) COLLATE utf8_bin NOT NULL,
  objectPHID VARCHAR(64) COLLATE utf8_bin NOT NULL,
  viewPolicy VARCHAR(64) COLLATE utf8_bin NOT NULL,
  editPolicy VARCHAR(64) COLLATE utf8_bin NOT NULL,
  commentPHID VARCHAR(64) COLLATE utf8_bin DEFAULT NULL,
  commentVersion INT UNSIGNED NOT NULL,
  transactionType VARCHAR(32) COLLATE utf8_bin NOT NULL,
  oldValue LONGTEXT COLLATE utf8_bin NOT NULL,
  newValue LONGTEXT COLLATE utf8_bin NOT NULL,
  contentSource LONGTEXT COLLATE utf8_bin NOT NULL,
  metadata LONGTEXT COLLATE utf8_bin NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
