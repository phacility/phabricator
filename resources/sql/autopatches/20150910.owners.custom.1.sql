CREATE TABLE {$NAMESPACE}_owners.owners_customfieldstorage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARBINARY(64) NOT NULL,
  fieldIndex BINARY(12) NOT NULL,
  fieldValue LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  UNIQUE KEY (objectPHID, fieldIndex)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};

CREATE TABLE {$NAMESPACE}_owners.owners_customfieldstringindex (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARBINARY(64) NOT NULL,
  indexKey BINARY(12) NOT NULL,
  indexValue LONGTEXT NOT NULL COLLATE {$COLLATE_SORT},
  KEY `key_join` (objectPHID, indexKey, indexValue(64)),
  KEY `key_find` (indexKey, indexValue(64))
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};

CREATE TABLE {$NAMESPACE}_owners.owners_customfieldnumericindex (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARBINARY(64) NOT NULL,
  indexKey BINARY(12) NOT NULL,
  indexValue BIGINT NOT NULL,
  KEY `key_join` (objectPHID, indexKey, indexValue),
  KEY `key_find` (indexKey, indexValue)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
