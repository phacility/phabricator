TRUNCATE TABLE {$NAMESPACE}_almanac.almanac_device;

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  CHANGE name name VARCHAR(128) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  ADD nameIndex BINARY(12) NOT NULL;

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  ADD mailKey BINARY(20) NOT NULL;

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  ADD UNIQUE KEY `key_name` (nameIndex);

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  ADD KEY `key_nametext` (name);

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  ADD viewPolicy VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_almanac.almanac_device
  ADD editPolicy VARBINARY(64) NOT NULL;
