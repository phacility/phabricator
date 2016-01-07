CREATE TABLE {$NAMESPACE}_search.search_editengineconfiguration (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  engineKey VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT},
  builtinKey VARCHAR(64) COLLATE {$COLLATE_TEXT},
  name VARCHAR(255) NOT NULL COLLATE {$COLLATE_TEXT},
  viewPolicy VARBINARY(64) NOT NULL,
  editPolicy VARBINARY(64) NOT NULL,
  properties LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  isDisabled BOOL NOT NULL DEFAULT 0,
  isDefault BOOL NOT NULL DEFAULT 0,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_engine` (engineKey, builtinKey),
  KEY `key_default` (engineKey, isDefault, isDisabled)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
