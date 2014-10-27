CREATE TABLE {$NAMESPACE}_almanac.almanac_binding (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  servicePHID VARBINARY(64) NOT NULL,
  devicePHID VARBINARY(64) NOT NULL,
  interfacePHID VARBINARY(64) NOT NULL,
  mailKey BINARY(20) NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_service` (servicePHID, interfacePHID),
  KEY `key_device` (devicePHID),
  KEY `key_interface` (interfacePHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
