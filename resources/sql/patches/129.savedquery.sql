CREATE TABLE {$NAMESPACE}_maniphest.maniphest_savedquery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userPHID varchar(64) COLLATE utf8_bin NOT NULL,
  queryKey varchar(64) COLLATE utf8_bin NOT NULL,
  name varchar(128) COLLATE utf8_general_ci NOT NULL,
  isDefault BOOL NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  KEY (userPHID, name),
  KEY (userPHID, isDefault, name)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
