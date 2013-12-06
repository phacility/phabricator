CREATE TABLE {$NAMESPACE}_repository.repository_pushlog (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  epoch INT UNSIGNED NOT NULL,
  repositoryPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  pusherPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  remoteAddress INT UNSIGNED,
  remoteProtocol VARCHAR(32),
  transactionKey CHAR(12) NOT NULL COLLATE latin1_bin,
  refType VARCHAR(12) NOT NULL COLLATE utf8_bin,
  refNameHash VARCHAR(12) COLLATE latin1_bin,
  refNameRaw LONGTEXT COLLATE latin1_bin,
  refNameEncoding VARCHAR(16) COLLATE utf8_bin,
  refOld VARCHAR(40) COLLATE latin1_bin,
  refNew VARCHAR(40) NOT NULL COLLATE latin1_bin,
  mergeBase VARCHAR(40) COLLATE latin1_bin,
  changeFlags INT UNSIGNED NOT NULL,
  rejectCode INT UNSIGNED NOT NULL,
  rejectDetails VARCHAR(64) COLLATE utf8_bin,

  KEY `key_repository` (repositoryPHID),
  KEY `key_ref` (repositoryPHID, refNew),
  KEY `key_pusher` (pusherPHID),
  KEY `key_name` (repositoryPHID, refNameHash)

) ENGINE=InnoDB, COLLATE=utf8_general_ci;
