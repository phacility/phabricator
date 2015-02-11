create table {$NAMESPACE}_repository.repository_commitdata (
  id int unsigned not null auto_increment primary key,
  commitID int unsigned not null,
  authorName varchar(255) not null,
  commitMessage longblob not null,
  unique key (commitID),
  key (authorName(128))
);

ALTER TABLE {$NAMESPACE}_worker.worker_task drop priority;
ALTER TABLE {$NAMESPACE}_worker.worker_task drop key leaseOwner;
ALTER TABLE {$NAMESPACE}_worker.worker_task add key (leaseOwner(16));

create table {$NAMESPACE}_repository.repository_path (
  id int unsigned not null auto_increment primary key,
  path varchar(128) binary not null,
  unique key (path)
);

create table {$NAMESPACE}_repository.repository_pathchange (
  repositoryID int unsigned NOT NULL,
  pathID int unsigned NOT NULL,
  commitID int unsigned NOT NULL,
  targetPathID int unsigned,
  targetCommitID int unsigned,
  changeType int unsigned NOT NULL,
  fileType int unsigned NOT NULL,
  isDirect bool NOT NULL,
  commitSequence int unsigned NOT NULL,
  primary key (commitID, pathID),
  key (repositoryID, pathID, commitSequence)
);

create table {$NAMESPACE}_repository.repository_filesystem (
  repositoryID int unsigned not null,
  parentID int unsigned not null,
  svnCommit int unsigned not null,
  pathID int unsigned not null,
  existed bool not null,
  fileType int unsigned not null,
  primary key (repositoryID, parentID, svnCommit, pathID)
);

alter table {$NAMESPACE}_repository.repository_filesystem add key (repositoryID, svnCommit);

truncate {$NAMESPACE}_repository.repository_commit;
alter table {$NAMESPACE}_repository.repository_commit
  change repositoryPHID repositoryID int unsigned not null;
alter table {$NAMESPACE}_repository.repository_commit drop key repositoryPHID;
alter table {$NAMESPACE}_repository.repository_commit add unique key
  (repositoryID, commitIdentifier(16));
alter table {$NAMESPACE}_repository.repository_commit add key
  (repositoryID, epoch);

alter table {$NAMESPACE}_repository.repository_filesystem
  add key (repositoryID, pathID, svnCommit);
