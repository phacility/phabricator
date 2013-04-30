CREATE TABLE {$NAMESPACE}_search.search_savedquery (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  engineClassName VARCHAR(255) NOT NULL COLLATE utf8_bin,
  parameters LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT(10) UNSIGNED NOT NULL,
  dateModified INT(10) UNSIGNED NOT NULL,
  queryKey VARCHAR(12) NOT NULL COLLATE utf8_bin,
  PRIMARY KEY(id),
  UNIQUE KEY key_queryKey (queryKey)
)
ENGINE=InnoDB, COLLATE utf8_general_ci
