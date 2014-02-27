CREATE TABLE `{$NAMESPACE}_phame`.`phame_post` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `phid` VARCHAR(64) BINARY NOT NULL COLLATE utf8_bin,
  `bloggerPHID` VARCHAR(64) BINARY NOT NULL COLLATE utf8_bin,
  `title` VARCHAR(255) NOT NULL,
  `phameTitle` VARCHAR(64) NOT NULL,
  `body` LONGTEXT COLLATE utf8_general_ci,
  `visibility` INT UNSIGNED NOT NULL DEFAULT 0,
  `configData` LONGTEXT COLLATE utf8_general_ci,
  `datePublished` INT UNSIGNED NOT NULL,
  `dateCreated` INT UNSIGNED NOT NULL,
  `dateModified` INT UNSIGNED NOT NULL,
  KEY `bloggerPosts` (`bloggerPHID`, `visibility`, `datePublished`, `id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `phameTitle` (`bloggerPHID`, `phameTitle`)
) ENGINE=InnoDB;
