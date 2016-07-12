CREATE TABLE {$NAMESPACE}_repository.repository_arcanistproject (
  id int unsigned not null auto_increment primary key,
  phid varchar(64) binary not null,
  unique key(phid),
  name varchar(255) COLLATE `binary` not null,
  unique key (name),
  repositoryID int unsigned
);

ALTER TABLE {$NAMESPACE}_repository.repository
  ADD uuid varchar(64) binary;

ALTER TABLE {$NAMESPACE}_differential.differential_diff
  CHANGE arcanistProject arcanistProjectPHID varchar(64) binary;

ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD repositoryUUID varchar(64) binary;
