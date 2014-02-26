CREATE TABLE `{$NAMESPACE}_ponder`.`ponder_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `targetPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `content` longtext CHARACTER SET utf8 NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `authorPHID` (`authorPHID`),
  KEY `targetPHID` (`targetPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
