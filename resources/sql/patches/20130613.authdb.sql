CREATE TABLE {$NAMESPACE}_auth.auth_providerconfig (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  providerClass VARCHAR(128) NOT NULL COLLATE utf8_bin,
  providerType VARCHAR(64) NOT NULL COLLATE utf8_bin,
  providerDomain VARCHAR(128) NOT NULL COLLATE utf8_bin,
  isEnabled BOOL NOT NULL,
  shouldAllowLogin BOOL NOT NULL,
  shouldAllowRegistration BOOL NOT NULL,
  shouldAllowLink BOOL NOT NULL,
  shouldAllowUnlink BOOL NOT NULL,
  properties LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_class` (providerClass),
  UNIQUE KEY `key_provider` (providerType, providerDomain)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_auth.auth_providerconfigtransaction (
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
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  contentSource LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_object` (objectPHID)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
