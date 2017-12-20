CREATE TABLE {$NAMESPACE}_owners.owners_package_fngrams_common (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ngram CHAR(3) NOT NULL COLLATE {$COLLATE_TEXT},
  needsCollection BOOL NOT NULL,
  UNIQUE KEY `key_ngram` (ngram),
  KEY `key_collect` (needsCollection)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
