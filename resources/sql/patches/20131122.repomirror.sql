CREATE TABLE {$NAMESPACE}_repository.repository_mirror (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  repositoryPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  remoteURI VARCHAR(255) NOT NULL COLLATE utf8_bin,
  credentialPHID VARCHAR(64) COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_repository` (repositoryPHID)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
