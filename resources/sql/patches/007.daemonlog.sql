create table {$NAMESPACE}_daemon.daemon_log (
  id int unsigned not null auto_increment primary key,
  daemon varchar(255) not null,
  host varchar(255) not null,
  pid int unsigned not null,
  argv varchar(512) not null,
  dateCreated int unsigned not null,
  dateModified int unsigned not null
);

create table {$NAMESPACE}_daemon.daemon_logevent (
  id int unsigned not null auto_increment primary key,
  logID int unsigned not null,
  logType varchar(4) not null,
  message longblob not null,
  epoch int unsigned not null,
  key (logID, epoch)
);
