CREATE TABLE {$NAMESPACE}_multimeter.multimeter_event (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  eventType INT UNSIGNED NOT NULL,
  eventLabelID INT UNSIGNED NOT NULL,
  resourceCost BIGINT NOT NULL,
  sampleRate INT UNSIGNED NOT NULL,
  eventContextID INT UNSIGNED NOT NULL,
  eventHostID INT UNSIGNED NOT NULL,
  eventViewerID INT UNSIGNED NOT NULL,
  epoch INT UNSIGNED NOT NULL,
  requestKey BINARY(12) NOT NULL,
  KEY `key_request` (requestKey),
  KEY `key_type` (eventType, epoch)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
