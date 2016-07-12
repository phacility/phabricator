CREATE TABLE {$NAMESPACE}_almanac.almanac_interface (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  devicePHID VARBINARY(64) NOT NULL,
  networkPHID VARBINARY(64) NOT NULL,
  address VARCHAR(128) NOT NULL COLLATE utf8_bin,
  port INT UNSIGNED NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_location` (networkPHID, address, port),
  KEY `key_device` (devicePHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
