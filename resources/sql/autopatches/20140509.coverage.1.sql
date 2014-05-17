CREATE TABLE {$NAMESPACE}_repository.repository_coverage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branchID INT UNSIGNED NOT NULL,
  commitID INT UNSIGNED NOT NULL,
  pathID INT UNSIGNED NOT NULL,
  coverage LONGTEXT NOT NULL COLLATE latin1_bin,
  KEY `key_path` (branchID, pathID, commitID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
