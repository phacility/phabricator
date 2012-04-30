CREATE TABLE {$NAMESPACE}_user.user_preferences (
       id int unsigned not null auto_increment primary key,
       userPHID varchar(64) binary not null,
       preferences longblob not null,
       unique key (userPHID)
);
