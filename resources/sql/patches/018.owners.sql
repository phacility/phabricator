CREATE TABLE {$NAMESPACE}_owners.owners_package (
  id int unsigned not null auto_increment primary key,
  phid varchar(64) binary not null,
  unique key(phid),
  name varchar(255) COLLATE `binary` not null,
  unique key(name),
  description text not null,
  primaryOwnerPHID varchar(64) binary
);

CREATE TABLE {$NAMESPACE}_owners.owners_owner (
  id int unsigned not null auto_increment primary key,
  packageID int unsigned not null,
  userPHID varchar(64) binary not null,
  UNIQUE KEY(packageID, userPHID),
  KEY(userPHID)
);

CREATE TABLE {$NAMESPACE}_owners.owners_path (
  id int unsigned not null auto_increment primary key,
  packageID int unsigned not null,
  key(packageID),
  repositoryPHID varchar(64) binary not null,
  path varchar(255) not null
);
