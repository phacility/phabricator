CREATE TABLE {$NAMESPACE}_almanac.almanac_networkname_ngrams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objectID INT UNSIGNED NOT NULL,
  ngram CHAR(3) NOT NULL COLLATE {$COLLATE_TEXT},
  KEY `key_object` (objectID),
  KEY `key_ngram` (ngram, objectID)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
