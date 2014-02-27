CREATE TABLE {$NAMESPACE}_flag.flag (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ownerPHID varchar(64) COLLATE utf8_bin NOT NULL,
  type varchar(4) COLLATE utf8_bin NOT NULL,
  objectPHID varchar(64) COLLATE utf8_bin NOT NULL,
  reasonPHID varchar(64) COLLATE utf8_bin NOT NULL,
  color INT UNSIGNED NOT NULL,
  note varchar(255) COLLATE utf8_general_ci,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY (ownerPHID, type, objectPHID),
  KEY (objectPHID)
) ENGINE=InnoDB;
