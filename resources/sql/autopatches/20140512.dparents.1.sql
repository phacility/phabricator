CREATE TABLE {$NAMESPACE}_repository.repository_parents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  childCommitID INT UNSIGNED NOT NULL,
  parentCommitID INT UNSIGNED NOT NULL,
  UNIQUE `key_child` (childCommitID, parentCommitID),
  KEY `key_parent` (parentCommitID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
