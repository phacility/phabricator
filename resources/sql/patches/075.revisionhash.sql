CREATE TABLE {$NAMESPACE}_differential.differential_revisionhash (
  revisionID INT UNSIGNED NOT NULL,
  type CHAR(4) BINARY NOT NULL,
  hash VARCHAR(40) BINARY NOT NULL,
  KEY (type, hash),
  KEY (revisionID)
) ENGINE=InnoDB;
