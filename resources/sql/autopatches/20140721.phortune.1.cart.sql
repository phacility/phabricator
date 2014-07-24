CREATE TABLE {$NAMESPACE}_phortune.phortune_cart (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  accountPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_account` (accountPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
