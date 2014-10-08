CREATE TABLE {$NAMESPACE}_project.project_datasourcetoken (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  projectID INT UNSIGNED NOT NULL,
  token VARCHAR(128) NOT NULL COLLATE utf8_general_ci,
  UNIQUE KEY (token, projectID),
  KEY (projectID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
