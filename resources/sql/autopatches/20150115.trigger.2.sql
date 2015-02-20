CREATE TABLE {$NAMESPACE}_worker.worker_triggerevent (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  triggerID INT UNSIGNED NOT NULL,
  lastEventEpoch INT UNSIGNED,
  nextEventEpoch INT UNSIGNED,
  UNIQUE KEY `key_trigger` (triggerID),
  KEY `key_next` (nextEventEpoch)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
