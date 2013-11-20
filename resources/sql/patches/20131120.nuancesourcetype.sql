ALTER TABLE {$NAMESPACE}_nuance.nuance_source
  DROP KEY key_type;

ALTER TABLE {$NAMESPACE}_nuance.nuance_source
  DROP COLUMN type;

ALTER TABLE {$NAMESPACE}_nuance.nuance_source
  ADD type VARCHAR(32) NOT NULL COLLATE utf8_bin AFTER name;

ALTER TABLE {$NAMESPACE}_nuance.nuance_source
  ADD KEY `key_type` (type, dateModified);
