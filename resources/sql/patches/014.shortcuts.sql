CREATE TABLE {$NAMESPACE}_repository.repository_shortcut (
  id int unsigned not null auto_increment primary key,
  name varchar(255) not null,
  href varchar(255) not null,
  description varchar(255) not null,
  sequence int unsigned not null
);
