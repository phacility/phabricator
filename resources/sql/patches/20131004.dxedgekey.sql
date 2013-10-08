ALTER TABLE {$NAMESPACE}_differential.edge
  ADD UNIQUE KEY `key_dst` (dst, type, src);
