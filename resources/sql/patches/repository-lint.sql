CREATE TABLE {$NAMESPACE}_repository.repository_branch (
  id int unsigned NOT NULL AUTO_INCREMENT,
  repositoryID int unsigned NOT NULL,
  name varchar(255) NOT NULL,
  lintCommit varchar(40),
  dateCreated int unsigned NOT NULL,
  dateModified int unsigned NOT NULL,
  UNIQUE (repositoryID, name),
  PRIMARY KEY (id)
);

CREATE TABLE {$NAMESPACE}_repository.repository_lintmessage (
  id int unsigned NOT NULL AUTO_INCREMENT,
  branchID int unsigned NOT NULL,
  path varchar(512) NOT NULL,
  line int unsigned NOT NULL,
  code varchar(32) NOT NULL,
  severity varchar(16) NOT NULL,
  name varchar(255) NOT NULL,
  description text NOT NULL,
  INDEX (branchID, path(64)),
  INDEX (branchID, code, path(64)),
  PRIMARY KEY (id)
);
