CREATE TABLE {$NAMESPACE}_system.system_destructionlog (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectClass VARCHAR(128) NOT NULL COLLATE utf8_bin,
  rootLogID INT UNSIGNED,
  objectPHID VARCHAR(64) COLLATE utf8_bin,
  objectMonogram VARCHAR(64) COLLATE utf8_bin,
  epoch INT UNSIGNED NOT NULL,
  KEY `key_epoch` (epoch)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
