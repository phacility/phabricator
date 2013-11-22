CREATE TABLE {$NAMESPACE}_passphrase.passphrase_credential (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) NOT NULL,
  credentialType VARCHAR(64) NOT NULL COLLATE utf8_bin,
  providesType VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  description LONGTEXT NOT NULL COLLATE utf8_bin,
  username VARCHAR(255) NOT NULL,
  secretID INT UNSIGNED,
  isDestroyed BOOL NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_type` (credentialType),
  KEY `key_provides` (providesType),
  UNIQUE KEY `key_secret` (secretID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_passphrase.passphrase_secret (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  secretData LONGBLOB NOT NULL
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_passphrase.passphrase_credentialtransaction (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  commentPHID VARCHAR(64) COLLATE utf8_bin,
  commentVersion INT UNSIGNED NOT NULL,
  transactionType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  oldValue LONGTEXT NOT NULL COLLATE utf8_bin,
  newValue LONGTEXT NOT NULL COLLATE utf8_bin,
  contentSource LONGTEXT NOT NULL COLLATE utf8_bin,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_object` (objectPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
