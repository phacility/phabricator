CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_role` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `edge` (
  `src` varbinary(64) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  UNIQUE KEY `key_dst` (`dst`,`type`,`src`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(32) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `joinPolicy` varbinary(64) NOT NULL,
  `isMembershipLocked` tinyint(1) NOT NULL DEFAULT '0',
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `icon` varchar(32) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `color` varchar(32) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `primarySlug` varchar(128) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `parentRolePHID` varbinary(64) DEFAULT NULL,
  `hasWorkboard` tinyint(1) NOT NULL,
  `hasMilestones` tinyint(1) NOT NULL,
  `hasSubroles` tinyint(1) NOT NULL,
  `milestoneNumber` int(10) unsigned DEFAULT NULL,
  `rolePath` varbinary(64) NOT NULL,
  `roleDepth` int(10) unsigned NOT NULL,
  `rolePathKey` binary(4) NOT NULL,
  `properties` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  `subtype` varchar(64) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_pathkey` (`rolePathKey`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_primaryslug` (`primarySlug`),
  UNIQUE KEY `key_milestone` (`parentRolePHID`,`milestoneNumber`),
  KEY `key_icon` (`icon`),
  KEY `key_color` (`color`),
  KEY `key_path` (`rolePath`,`roleDepth`),
  KEY `key_space` (`spacePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_column` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `rolePHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `properties` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `proxyPHID` varbinary(64) DEFAULT NULL,
  `triggerPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_proxy` (`rolePHID`,`proxyPHID`),
  KEY `key_status` (`rolePHID`,`status`,`sequence`),
  KEY `key_sequence` (`rolePHID`,`sequence`),
  KEY `key_trigger` (`triggerPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_columnposition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boardPHID` varbinary(64) NOT NULL,
  `columnPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `boardPHID` (`boardPHID`,`columnPHID`,`objectPHID`),
  KEY `objectPHID` (`objectPHID`,`boardPHID`),
  KEY `boardPHID_2` (`boardPHID`,`columnPHID`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_columntransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentPHID` varbinary(64) DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `oldValue` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `newValue` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldValue` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_datasourcetoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `roleID` int(10) unsigned NOT NULL,
  `token` varchar(128) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`,`roleID`),
  KEY `roleID` (`roleID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_role_fdocument` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `isClosed` tinyint(1) NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `epochCreated` int(10) unsigned NOT NULL,
  `epochModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_object` (`objectPHID`),
  KEY `key_author` (`authorPHID`),
  KEY `key_owner` (`ownerPHID`),
  KEY `key_created` (`epochCreated`),
  KEY `key_modified` (`epochModified`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_role_ffield` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentID` int(10) unsigned NOT NULL,
  `fieldKey` varchar(4) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `rawCorpus` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `termCorpus` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `normalCorpus` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_documentfield` (`documentID`,`fieldKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_role_fngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentID` int(10) unsigned NOT NULL,
  `ngram` char(3) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_ngram` (`ngram`,`documentID`),
  KEY `key_object` (`documentID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_role_fngrams_common` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ngram` char(3) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `needsCollection` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_ngram` (`ngram`),
  KEY `key_collect` (`needsCollection`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_slug` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rolePHID` varbinary(64) NOT NULL,
  `slug` varchar(128) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_slug` (`slug`),
  KEY `key_rolePHID` (`rolePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentPHID` varbinary(64) DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `oldValue` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `newValue` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_trigger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `ruleset` longtext CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_triggertransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentPHID` varbinary(64) DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `oldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `newValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

USE `{$NAMESPACE}_role`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `role_triggerusage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `triggerPHID` varbinary(64) NOT NULL,
  `examplePHID` varbinary(64) DEFAULT NULL,
  `columnCount` int(10) unsigned NOT NULL,
  `activeColumnCount` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_trigger` (`triggerPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};
