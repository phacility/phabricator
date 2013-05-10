CREATE TABLE {$NAMESPACE}_search.search_namedquery (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  userPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  engineClassName VARCHAR(128) NOT NULL COLLATE utf8_bin,
  queryName VARCHAR(255) NOT NULL COLLATE utf8_bin,
  queryKey VARCHAR(12) NOT NULL COLLATE utf8_bin,
  dateCreated INT(10) UNSIGNED NOT NULL,
  dateModified INT(10) UNSIGNED NOT NULL,
  UNIQUE KEY `key_userquery` (userPHID, engineClassName, queryKey),
  PRIMARY KEY(id)
)
ENGINE=InnoDB, COLLATE utf8_general_ci
