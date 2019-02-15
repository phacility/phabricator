CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_string (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  stringIndex BINARY(12) NOT NULL,
  stringValue LONGTEXT NOT NULL,
  UNIQUE KEY `key_string` (stringIndex)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
