CREATE TABLE {$NAMESPACE}_repository.repository_uriindex (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  repositoryPHID VARBINARY(64) NOT NULL,
  repositoryURI LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  KEY `key_repository` (repositoryPHID),
  KEY `key_uri` (repositoryURI(128))
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
