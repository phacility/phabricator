CREATE TABLE {$NAMESPACE}_differential.differential_commit (
  revisionID int unsigned not null,
  commitPHID varchar(64) binary not null,
  primary key (revisionID, commitPHID),
  unique key (commitPHID)
);
