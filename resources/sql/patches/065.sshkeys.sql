CREATE TABLE {$NAMESPACE}_user.user_sshkey (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userPHID varchar(64) BINARY NOT NULL,
  key (userPHID),
  name varchar(255),
  keyType varchar(255),
  keyBody LONGBLOB,
  unique key (keyBody(128)),
  keyComment varchar(255),
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
