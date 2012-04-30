CREATE TABLE {$NAMESPACE}_differential.differential_affectedpath (
  repositoryID INT UNSIGNED NOT NULL,
  pathID INT UNSIGNED NOT NULL,
  epoch INT UNSIGNED NOT NULL,
  KEY (repositoryID, pathID, epoch),
  revisionID INT UNSIGNED NOT NULL,
  KEY (revisionID)
) ENGINE=InnoDB;
