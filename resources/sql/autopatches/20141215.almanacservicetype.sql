ALTER TABLE {$NAMESPACE}_almanac.almanac_service
  ADD serviceClass VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT};

ALTER TABLE {$NAMESPACE}_almanac.almanac_service
  ADD KEY `key_class` (serviceClass);

UPDATE {$NAMESPACE}_almanac.almanac_service
  SET serviceClass = 'AlmanacCustomServiceType' WHERE serviceClass = '';
