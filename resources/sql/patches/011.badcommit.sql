CREATE TABLE {$NAMESPACE}_repository.repository_badcommit (
  fullCommitName varchar(255) COLLATE `binary` not null primary key,
  description longblob not null
);
