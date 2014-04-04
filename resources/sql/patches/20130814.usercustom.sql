CREATE TABLE {$NAMESPACE}_user.user_configuredcustomfieldstorage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  fieldIndex CHAR(12) NOT NULL COLLATE utf8_bin,
  fieldValue LONGTEXT NOT NULL,
  UNIQUE KEY (objectPHID, fieldIndex)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
