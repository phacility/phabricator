CREATE TABLE {$NAMESPACE}_almanac.almanac_service (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  name VARCHAR(128) NOT NULL COLLATE utf8_bin,
  nameIndex BINARY(12) NOT NULL,
  mailKey BINARY(20) NOT NULL,
  viewPolicy VARBINARY(64) NOT NULL,
  editPolicy VARBINARY(64) NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_name` (nameIndex),
  KEY `key_nametext` (name)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
