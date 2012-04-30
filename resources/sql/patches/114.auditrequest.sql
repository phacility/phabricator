CREATE TABLE {$NAMESPACE}_repository.repository_auditrequest (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  auditorPHID varchar(64) BINARY NOT NULL,
  commitPHID varchar(64) BINARY NOT NULL,
  auditStatus varchar(64) NOT NULL,
  auditReasons LONGBLOB NOT NULL,
  KEY (commitPHID),
  KEY (auditorPHID, auditStatus)
) ENGINE=InnoDB;

INSERT INTO {$NAMESPACE}_repository.repository_auditrequest
    (auditorPHID, commitPHID, auditStatus, auditReasons)
  SELECT packagePHID, commitPHID, auditStatus, auditReasons
    FROM {$NAMESPACE}_owners.owners_packagecommitrelationship;

DROP TABLE {$NAMESPACE}_owners.owners_packagecommitrelationship;
