CREATE TABLE {$NAMESPACE}_fund.fund_initiative (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) NOT NULL,
  ownerPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  description LONGTEXT NOT NULL,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  status VARCHAR(32) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_status` (status),
  KEY `key_owner` (ownerPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
