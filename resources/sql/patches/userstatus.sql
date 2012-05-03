CREATE TABLE {$NAMESPACE}_user.user_status (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) NOT NULL,
  `dateFrom` int unsigned NOT NULL,
  `dateTo` int unsigned NOT NULL,
  `status` tinyint unsigned NOT NULL,
  `dateCreated` int unsigned NOT NULL,
  `dateModified` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `userPHID_dateFrom` (`userPHID`, `dateTo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
