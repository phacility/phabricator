CREATE TABLE {$NAMESPACE}_phortune.phortune_charge (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  accountPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  cartPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  paymentMethodPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  amountInCents INT NOT NULL,
  status VARCHAR(32) NOT NULL COLLATE utf8_bin,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_cart` (cartPHID),
  KEY `key_account` (accountPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
