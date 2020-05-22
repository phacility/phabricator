CREATE TABLE {$NAMESPACE}_differential.differential_changeset_parse_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cacheIndex BINARY(12) NOT NULL,
  cache LONGBLOB NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_cacheIndex` (cacheIndex)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
