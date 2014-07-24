CREATE TABLE {$NAMESPACE}_phortune.phortune_purchase (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  productPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  accountPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  cartPHID VARCHAR(64) COLLATE utf8_bin,
  basePriceInCents INT NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  totalPriceInCents INT NOT NULL,
  status VARCHAR(32) NOT NULL COLLATE utf8_bin,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_cart` (cartPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
