CREATE TABLE {$NAMESPACE}_repository.repository_pushevent (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  repositoryPHID  VARCHAR(64) NOT NULL COLLATE utf8_bin,
  epoch INT UNSIGNED NOT NULL,
  pusherPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  remoteAddress INT UNSIGNED,
  remoteProtocol VARCHAR(32),
  rejectCode INT UNSIGNED NOT NULL,
  rejectDetails VARCHAR(64) COLLATE utf8_bin,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_repository` (repositoryPHID)

) ENGINE=InnoDB, COLLATE=utf8_general_ci;
