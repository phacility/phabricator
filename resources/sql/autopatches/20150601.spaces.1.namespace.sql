CREATE TABLE {$NAMESPACE}_spaces.spaces_namespace (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  namespaceName VARCHAR(255) NOT NULL COLLATE {$COLLATE_TEXT},
  viewPolicy VARBINARY(64) NOT NULL,
  editPolicy VARBINARY(64) NOT NULL,
  isDefaultNamespace BOOL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_default` (isDefaultNamespace)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
