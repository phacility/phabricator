CREATE TABLE {$NAMESPACE}_repository.repository_statusmessage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  repositoryID INT UNSIGNED NOT NULL,
  statusType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  statusCode VARCHAR(32) NOT NULL COLLATE utf8_bin,
  parameters LONGTEXT NOT NULL,
  epoch INT UNSIGNED NOT NULL,
  UNIQUE KEY (repositoryID, statusType)
) ENGINE=InnoDB, CHARSET utf8;
