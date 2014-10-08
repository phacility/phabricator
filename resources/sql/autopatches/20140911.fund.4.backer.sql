CREATE TABLE {$NAMESPACE}_fund.fund_backer (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  initiativePHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  backerPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  status VARCHAR(32) NOT NULL COLLATE utf8_bin,
  amountInCents INT UNSIGNED NOT NULL,
  properties LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_initiative` (initiativePHID),
  KEY `key_backer` (backerPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
