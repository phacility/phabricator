CREATE TABLE {$NAMESPACE}_project.project_slug (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  projectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  slug VARCHAR(128) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_slug` (slug),
  KEY `key_projectPHID` (projectPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
