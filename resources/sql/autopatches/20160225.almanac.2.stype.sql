ALTER TABLE {$NAMESPACE}_almanac.almanac_service
  CHANGE serviceClass serviceType VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT};
