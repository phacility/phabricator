CREATE TABLE {$NAMESPACE}_cache.cache_markupcache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cacheKey VARCHAR(128) NOT NULL collate utf8_bin,
  cacheData LONGTEXT NOT NULL COLLATE utf8_bin,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY (cacheKey),
  KEY (dateCreated)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
