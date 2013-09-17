CREATE TABLE {$NAMESPACE}_maniphest.maniphest_customfieldstorage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  fieldIndex CHAR(12) NOT NULL COLLATE utf8_bin,
  fieldValue LONGTEXT NOT NULL,
  UNIQUE KEY (objectPHID, fieldIndex)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_maniphest.maniphest_customfieldstringindex (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  indexKey VARCHAR(12) NOT NULL COLLATE utf8_bin,
  indexValue LONGTEXT NOT NULL COLLATE utf8_general_ci,

  KEY `key_join` (objectPHID, indexKey, indexValue(64)),
  KEY `key_find` (indexKey, indexValue(64))

) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_maniphest.maniphest_customfieldnumericindex (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  indexKey VARCHAR(12) NOT NULL COLLATE utf8_bin,
  indexValue BIGINT NOT NULL,

  KEY `key_join` (objectPHID, indexKey, indexValue),
  KEY `key_find` (indexKey, indexValue)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
