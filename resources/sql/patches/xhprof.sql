CREATE TABLE {$NAMESPACE}_xhprof.xhprof_sample (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `filePHID` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `sampleRate` INT NOT NULL,
  `usTotal` BIGINT UNSIGNED NOT NULL,
  `hostname` VARCHAR(255) COLLATE utf8_bin,
  `requestPath` VARCHAR(255) COLLATE utf8_bin,
  `controller` VARCHAR(255) COLLATE utf8_bin,
  `userPHID` VARCHAR(64) COLLATE utf8_bin,
  `dateCreated` BIGINT UNSIGNED NOT NULL,
  `dateModified` BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY (filePHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
