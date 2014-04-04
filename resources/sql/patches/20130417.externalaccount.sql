CREATE TABLE {$NAMESPACE}_user.externalaccount (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phid VARCHAR(64) COLLATE utf8_bin NOT NULL UNIQUE KEY,
  userPHID VARCHAR(64) COLLATE utf8_bin,
  accountType VARCHAR(16) COLLATE utf8_bin NOT NULL,
  accountDomain VARCHAR(64) COLLATE utf8_bin,
  accountSecret LONGTEXT COLLATE utf8_bin,
  accountID VARCHAR(160) COLLATE utf8_bin NOT NULL,
  displayName VARCHAR(256) COLLATE utf8_bin NOT NULL,
  UNIQUE KEY `account_details` (accountType, accountDomain, accountID)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;
