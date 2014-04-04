CREATE TABLE {$NAMESPACE}_project.project_column (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) NOT NULL,
  sequence INT UNSIGNED NOT NULL,
  projectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  UNIQUE KEY `key_sequence` (projectPHID, sequence),
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
