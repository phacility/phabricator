ALTER TABLE {$NAMESPACE}_phriction.phriction_content
  ADD UNIQUE KEY `key_version` (documentPHID, version);
