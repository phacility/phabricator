CREATE TABLE {$NAMESPACE}_differential.differential_auxiliaryfield (
  id INT UNSIGNED NOT NULL auto_increment PRIMARY KEY,
  revisionPHID varchar(64) BINARY NOT NULL,
  name VARCHAR(32) BINARY NOT NULL,
  value LONGBLOB NOT NULL,
  UNIQUE KEY (revisionPHID, name),
  KEY (name, value(64)),
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
) ENGINE = InnoDB;
