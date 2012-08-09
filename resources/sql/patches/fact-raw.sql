CREATE TABLE {$NAMESPACE}_fact.fact_raw (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `factType` VARCHAR(32) NOT NULL COLLATE utf8_bin,
  `objectPHID` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `objectA` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `valueX` BIGINT NOT NULL,
  `valueY` BIGINT NOT NULL,
  `epoch` INT UNSIGNED NOT NULL,
  KEY (objectPHID),
  KEY (factType, epoch),
  KEY (factType, objectA, epoch)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_fact.fact_aggregate (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `factType` VARCHAR(32) NOT NULL COLLATE utf8_bin,
  `objectPHID` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `valueX` BIGINT NOT NULL,
  UNIQUE KEY (factType, objectPHID),
  KEY (factType, valueX)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_fact.fact_cursor (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `position` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  UNIQUE KEY (name)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
