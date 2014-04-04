CREATE TABLE {$NAMESPACE}_doorkeeper.doorkeeper_externalobject (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  objectKey CHAR(12) NOT NULL COLLATE utf8_bin,
  applicationType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  applicationDomain VARCHAR(32) NOT NULL COLLATE utf8_bin,
  objectType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  objectID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  objectURI VARCHAR(128) COLLATE utf8_bin,
  importerPHID VARCHAR(64) COLLATE utf8_bin,
  properties LONGTEXT NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_object` (objectKey),
  KEY `key_full` (applicationType, applicationDomain, objectType, objectID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_doorkeeper.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type INT UNSIGNED NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_doorkeeper.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;
