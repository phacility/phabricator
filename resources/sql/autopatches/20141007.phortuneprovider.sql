CREATE TABLE {$NAMESPACE}_phortune.phortune_paymentproviderconfig (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  merchantPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  providerClassKey BINARY(12) NOT NULL,
  providerClass VARCHAR(128) NOT NULL COLLATE utf8_bin,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_merchant` (merchantPHID, providerClassKey)
) ENGINE=InnoDB, COLLATE=utf8_bin;
