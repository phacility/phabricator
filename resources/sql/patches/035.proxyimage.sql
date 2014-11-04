CREATE TABLE {$NAMESPACE}_file.file_proxyimage (
  id int unsigned not null primary key auto_increment,
  uri varchar(255) COLLATE `binary` not null,
  unique key(uri),
  filePHID varchar(64) binary not null
) ENGINE=InnoDB;
