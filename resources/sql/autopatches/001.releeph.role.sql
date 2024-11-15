USE `{$NAMESPACE}_releeph`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `releeph_role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `trunkBranch` varchar(255) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `createdByUserPHID` varbinary(64) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `details` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roleName` (`name`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};
