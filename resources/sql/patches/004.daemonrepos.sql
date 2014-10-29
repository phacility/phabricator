create table {$NAMESPACE}_repository.repository_commit (
  id int unsigned not null auto_increment primary key,
  repositoryPHID varchar(64) binary not null,
  phid varchar(64) binary not null,
  commitIdentifier varchar(40) binary not null,
  epoch int unsigned not null,
  unique key (phid),
  unique key (repositoryPHID, commitIdentifier)
);


create table {$NAMESPACE}_timeline.timeline_event (
  id int unsigned not null auto_increment primary key,
  type char(4) binary not null,
  key (type, id)
);

create table {$NAMESPACE}_timeline.timeline_eventdata (
  id int unsigned not null auto_increment primary key,
  eventID int unsigned not null,
  eventData longblob not null,
  unique key (eventID)
);

create table {$NAMESPACE}_timeline.timeline_cursor (
  name varchar(255) COLLATE `binary` not null primary key,
  position int unsigned not null
);
