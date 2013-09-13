CREATE TABLE {$NAMESPACE}_maniphest.maniphest_nameindex (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  indexedObjectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  indexedObjectName VARCHAR(128) NOT NULL,

  UNIQUE KEY `key_phid` (indexedObjectPHID),
  KEY `key_name` (indexedObjectName)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
