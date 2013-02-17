ALTER TABLE {$NAMESPACE}_cache.cache_general
  ADD KEY `key_ttl` (cacheExpires);
