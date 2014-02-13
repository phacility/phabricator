CREATE TABLE {$NAMESPACE}_repository.repository_refcursor (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  repositoryPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  refType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  refNameHash VARCHAR(12) NOT NULL COLLATE latin1_bin,
  refNameRaw LONGTEXT NOT NULL COLLATE latin1_bin,
  refNameEncoding VARCHAR(16) COLLATE utf8_bin,
  commitIdentifier VARCHAR(40) NOT NULL COLLATE utf8_bin,

  KEY `key_cursor` (repositoryPHID, refType, refNameHash)
) ENGINE=InnoDB, COLLATE=utf8_general_ci;
