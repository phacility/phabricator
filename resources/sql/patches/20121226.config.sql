CREATE TABLE {$NAMESPACE}_config.config_entry (
  `id` INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `phid` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `namespace` VARCHAR(64) BINARY NOT NULL COLLATE utf8_bin,
  `configKey` VARCHAR(64) BINARY NOT NULL COLLATE utf8_bin,
  `value` LONGTEXT NOT NULL,
  `isDeleted` BOOL NOT NULL,
  `dateCreated` INT UNSIGNED NOT NULL,
  `dateModified` INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`namespace`, `configKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
