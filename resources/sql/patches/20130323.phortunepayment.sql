CREATE TABLE {$NAMESPACE}_phortune.phortune_paymentmethod (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) NOT NULL,
  status VARCHAR(64) NOT NULL COLLATE utf8_bin,
  accountPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  expiresEpoch INT UNSIGNED,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_account` (accountPHID, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
