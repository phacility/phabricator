create table {$NAMESPACE}_worker.worker_task (
  id int unsigned not null auto_increment primary key,
  taskClass varchar(255) not null,
  leaseOwner varchar(255),
  leaseExpires int unsigned,
  priority bigint unsigned not null,
  failureCount int unsigned not null,
  key(taskClass(128)),
  key(leaseOwner(128)),
  key(leaseExpires)
);

create table {$NAMESPACE}_worker.worker_taskdata (
  id int unsigned not null auto_increment primary key,
  taskID int unsigned not null,
  data longblob not null,
  unique key (taskID)
);
