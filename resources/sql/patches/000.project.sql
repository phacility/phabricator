create table {$NAMESPACE}_project.project (
  id int unsigned not null auto_increment primary key,
  name varchar(255) COLLATE `binary` not null,
  unique key (name),
  phid varchar(64) binary not null,
  authorPHID varchar(64) binary not null,
  dateCreated int unsigned not null,
  dateModified int unsigned not null
);
create table {$NAMESPACE}_project.project_profile (
  id int unsigned not null auto_increment primary key,
  projectPHID varchar(64) binary not null,
  unique key (projectPHID),
  blurb longtext not null,
  profileImagePHID varchar(64) binary,
  dateCreated int unsigned not null,
  dateModified int unsigned not null
);
create table {$NAMESPACE}_project.project_affiliation (
  id int unsigned not null auto_increment primary key,
  projectPHID varchar(64) binary not null,
  userPHID varchar(64) binary not null,
  unique key (projectPHID, userPHID),
  key (userPHID),
  role varchar(255) not null,
  status varchar(32) not null,
  dateCreated int unsigned not null,
  dateModified int unsigned not null
);
