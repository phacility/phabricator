CREATE TABLE {$NAMESPACE}_repository.repository_workingcopyversion (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  repositoryPHID VARBINARY(64) NOT NULL,
  devicePHID VARBINARY(64) NOT NULL,
  repositoryVersion INT UNSIGNED NOT NULL,
  isWriting BOOL NOT NULL,
  UNIQUE KEY `key_workingcopy` (repositoryPHID, devicePHID)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
