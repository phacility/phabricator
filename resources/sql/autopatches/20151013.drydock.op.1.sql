CREATE TABLE {$NAMESPACE}_drydock.drydock_repositoryoperation (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  authorPHID VARBINARY(64) NOT NULL,
  objectPHID VARBINARY(64) NOT NULL,
  repositoryPHID VARBINARY(64) NOT NULL,
  repositoryTarget LONGBLOB NOT NULL,
  operationType VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT},
  operationState VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT},
  properties LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_object` (objectPHID),
  KEY `key_repository` (repositoryPHID, operationState)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
