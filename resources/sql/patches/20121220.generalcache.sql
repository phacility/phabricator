CREATE TABLE {$NAMESPACE}_cache.cache_general (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cacheKeyHash CHAR(12) BINARY NOT NULL,
  cacheKey VARCHAR(128) NOT NULL COLLATE utf8_bin,
  cacheFormat VARCHAR(16) NOT NULL COLLATE utf8_bin,
  cacheData LONGBLOB NOT NULL,
  cacheCreated INT UNSIGNED NOT NULL,
  cacheExpires INT UNSIGNED,
  KEY `key_cacheCreated` (cacheCreated),
  UNIQUE KEY `key_cacheKeyHash` (cacheKeyHash)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
