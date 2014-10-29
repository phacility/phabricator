create table {$NAMESPACE}_maniphest.maniphest_taskauxiliarystorage
    (id int unsigned not null auto_increment primary key,
    taskPHID varchar(64) binary not null,
    name varchar(255) COLLATE `binary` not null,
    value varchar(255) not null,
    unique key (taskPHID,name),
    dateCreated int unsigned not null,
    dateModified int unsigned not null)
    ENGINE = InnoDB;
