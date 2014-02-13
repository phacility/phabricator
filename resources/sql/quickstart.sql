CREATE DATABASE `{$NAMESPACE}_audit` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_audit`;

CREATE TABLE `audit_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `targetPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `actorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `action` varchar(64) NOT NULL,
  `content` longtext NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `targetPHID` (`targetPHID`,`actorPHID`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `audit_inlinecomment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `auditCommentID` int(10) unsigned DEFAULT NULL,
  `isNewFile` tinyint(1) NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `cache` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commitPHID` (`commitPHID`,`pathID`),
  KEY `authorPHID` (`authorPHID`,`commitPHID`,`auditCommentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_calendar` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_calendar`;

CREATE TABLE `calendar_holiday` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `day` date NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_chatlog` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_chatlog`;

CREATE TABLE `chatlog_channel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `serviceName` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `serviceType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `channelName` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_channel` (`channelName`,`serviceType`,`serviceName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `chatlog_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `epoch` int(10) unsigned NOT NULL,
  `author` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(4) NOT NULL,
  `message` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `loggedByPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `channelID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `channel` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_conduit` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_conduit`;

CREATE TABLE `conduit_certificatetoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `token` varchar(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `conduit_connectionlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client` varchar(255) DEFAULT NULL,
  `clientVersion` varchar(255) DEFAULT NULL,
  `clientDescription` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_created` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `conduit_methodcalllog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connectionID` bigint(20) unsigned DEFAULT NULL,
  `method` varchar(255) NOT NULL,
  `error` varchar(255) NOT NULL,
  `duration` bigint(20) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `callerPHID` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `key_created` (`dateCreated`),
  KEY `key_method` (`method`),
  KEY `key_callermethod` (`callerPHID`,`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_countdown` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_countdown`;

CREATE TABLE `countdown` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title` varchar(255) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `viewPolicy` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_daemon` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_daemon`;

CREATE TABLE `daemon_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `daemon` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `argv` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(8) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `daemon_logevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `logID` int(10) unsigned NOT NULL,
  `logType` varchar(4) NOT NULL,
  `message` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `logID` (`logID`,`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_differential` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_differential`;

CREATE TABLE `differential_affectedpath` (
  `repositoryID` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `revisionID` int(10) unsigned NOT NULL,
  KEY `repositoryID` (`repositoryID`,`pathID`,`epoch`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_auxiliaryfield` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `revisionPHID` (`revisionPHID`,`name`),
  KEY `name` (`name`,`value`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_changeset` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `diffID` int(10) unsigned NOT NULL,
  `oldFile` varchar(255) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `awayPaths` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `changeType` int(10) unsigned NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `oldProperties` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `newProperties` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `addLines` int(10) unsigned NOT NULL,
  `delLines` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `diffID` (`diffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_changeset_parse_cache` (
  `id` int(10) unsigned NOT NULL,
  `cache` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionID` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `action` varchar(64) NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `cache` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_commit` (
  `revisionID` int(10) unsigned NOT NULL,
  `commitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`revisionID`,`commitPHID`),
  UNIQUE KEY `commitPHID` (`commitPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldIndex` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_diff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `revisionID` int(10) unsigned DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `sourceMachine` varchar(255) DEFAULT NULL,
  `sourcePath` varchar(255) DEFAULT NULL,
  `sourceControlSystem` varchar(64) DEFAULT NULL,
  `sourceControlBaseRevision` varchar(255) DEFAULT NULL,
  `sourceControlPath` varchar(255) DEFAULT NULL,
  `lintStatus` int(10) unsigned NOT NULL,
  `unitStatus` int(10) unsigned NOT NULL,
  `lineCount` int(10) unsigned NOT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `bookmark` varchar(255) DEFAULT NULL,
  `parentRevisionID` int(10) unsigned DEFAULT NULL,
  `arcanistProjectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `creationMethod` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `repositoryUUID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_diffproperty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `diffID` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diffID` (`diffID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_hunk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `changesetID` int(10) unsigned NOT NULL,
  `changes` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `oldOffset` int(10) unsigned NOT NULL,
  `oldLen` int(10) unsigned NOT NULL,
  `newOffset` int(10) unsigned NOT NULL,
  `newLen` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `changesetID` (`changesetID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_inlinecomment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionID` int(10) unsigned NOT NULL,
  `commentID` int(10) unsigned DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `changesetID` int(10) unsigned NOT NULL,
  `isNewFile` tinyint(1) NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `cache` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  PRIMARY KEY (`id`),
  KEY `changesetID` (`changesetID`),
  KEY `commentID` (`commentID`),
  KEY `revisionID` (`revisionID`,`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_relationship` (
  `revisionID` int(10) unsigned NOT NULL,
  `relation` varchar(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `reasonPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`revisionID`,`relation`,`objectPHID`),
  KEY `objectPHID` (`objectPHID`,`relation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_revision` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `originalTitle` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `status` varchar(32) NOT NULL,
  `summary` longtext NOT NULL,
  `testPlan` text NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `lastReviewerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCommitted` int(10) unsigned DEFAULT NULL,
  `lineCount` int(10) unsigned DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `attached` longtext NOT NULL,
  `mailKey` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `branchName` varchar(255) DEFAULT NULL,
  `arcanistProjectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`,`status`),
  KEY `repositoryPHID` (`repositoryPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_revisionhash` (
  `revisionID` int(10) unsigned NOT NULL,
  `type` char(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `hash` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  KEY `type` (`type`,`hash`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `differential_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `revisionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `changesetID` int(10) unsigned DEFAULT NULL,
  `isNewFile` tinyint(1) NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `fixedState` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `hasReplies` tinyint(1) NOT NULL,
  `replyToCommentPHID` varchar(64) DEFAULT NULL,
  `legacyCommentID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  KEY `key_changeset` (`changesetID`),
  KEY `key_draft` (`authorPHID`,`transactionPHID`),
  KEY `key_revision` (`revisionPHID`),
  KEY `key_legacy` (`legacyCommentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  UNIQUE KEY `key_dst` (`dst`,`type`,`src`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_draft` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_draft`;

CREATE TABLE `draft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `draftKey` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `draft` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorPHID` (`authorPHID`,`draftKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_drydock` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_drydock`;

CREATE TABLE `drydock_blueprint` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `className` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `blueprintName` varchar(255) NOT NULL,
  `viewPolicy` varchar(64) NOT NULL,
  `editPolicy` varchar(64) NOT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `drydock_blueprinttransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `drydock_lease` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `resourceID` int(10) unsigned DEFAULT NULL,
  `status` int(10) unsigned NOT NULL,
  `until` int(10) unsigned DEFAULT NULL,
  `ownerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `attributes` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `taskID` int(10) unsigned DEFAULT NULL,
  `resourceType` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `drydock_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resourceID` int(10) unsigned DEFAULT NULL,
  `leaseID` int(10) unsigned DEFAULT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `message` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resourceID` (`resourceID`,`epoch`),
  KEY `leaseID` (`leaseID`,`epoch`),
  KEY `epoch` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `drydock_resource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `ownerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `status` int(10) unsigned NOT NULL,
  `type` varchar(64) NOT NULL,
  `attributes` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `capabilities` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `blueprintPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_feed` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_feed`;

CREATE TABLE `feed_storydata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `chronologicalKey` bigint(20) unsigned NOT NULL,
  `storyType` varchar(64) NOT NULL,
  `storyData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chronologicalKey` (`chronologicalKey`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `feed_storynotification` (
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `primaryObjectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `chronologicalKey` bigint(20) unsigned NOT NULL,
  `hasViewed` tinyint(1) NOT NULL,
  UNIQUE KEY `userPHID` (`userPHID`,`chronologicalKey`),
  KEY `userPHID_2` (`userPHID`,`hasViewed`,`primaryObjectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `feed_storyreference` (
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `chronologicalKey` bigint(20) unsigned NOT NULL,
  UNIQUE KEY `objectPHID` (`objectPHID`,`chronologicalKey`),
  KEY `chronologicalKey` (`chronologicalKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_file` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_file`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `mimeType` varchar(255) DEFAULT NULL,
  `byteSize` bigint(20) unsigned NOT NULL,
  `storageEngine` varchar(32) NOT NULL,
  `storageFormat` varchar(32) NOT NULL,
  `storageHandle` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `secretKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `contentHash` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ttl` int(10) unsigned DEFAULT NULL,
  `isExplicitUpload` tinyint(1) DEFAULT '1',
  `mailKey` varchar(20) NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`),
  KEY `contentHash` (`contentHash`),
  KEY `key_ttl` (`ttl`),
  KEY `key_dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `file_imagemacro` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `filePHID` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  `audioPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `audioBehavior` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_disabled` (`isDisabled`),
  KEY `key_dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `file_storageblob` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `file_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `file_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `file_transformedfile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `originalPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transform` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transformedPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `originalPHID` (`originalPHID`,`transform`),
  KEY `transformedPHID` (`transformedPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `macro_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `macro_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_flag` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_flag`;

CREATE TABLE `flag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ownerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `reasonPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `color` int(10) unsigned NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ownerPHID` (`ownerPHID`,`type`,`objectPHID`),
  KEY `objectPHID` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_harbormaster` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_harbormaster`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_build` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildablePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildPlanPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildStatus` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_buildable` (`buildablePHID`),
  KEY `key_plan` (`buildPlanPHID`),
  KEY `key_status` (`buildStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildablePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `containerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `buildStatus` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildableStatus` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isManualBuildable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_buildable` (`buildablePHID`),
  KEY `key_container` (`containerPHID`),
  KEY `key_manual` (`isManualBuildable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildartifact` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artifactType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `artifactIndex` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `artifactKey` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `artifactData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `buildTargetPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_artifact` (`artifactType`,`artifactIndex`),
  UNIQUE KEY `key_artifact_type` (`artifactType`,`artifactIndex`),
  KEY `key_garbagecollect` (`artifactType`,`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildcommand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `targetPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `command` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_target` (`targetPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `logSource` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `logType` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL,
  `live` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `buildTargetPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildlogchunk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `logID` int(10) unsigned NOT NULL,
  `encoding` varchar(30) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `size` mediumtext,
  `chunk` longblob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_log` (`logID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildplan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `planStatus` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_status` (`planStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildplantransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildstep` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildPlanPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `className` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_plan` (`buildPlanPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_buildtarget` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `buildStepPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `className` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `variables` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `targetStatus` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_build` (`buildPHID`,`buildStepPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_object` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `harbormaster_scratchtable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `lisk_counter` (
  `counterName` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `counterValue` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`counterName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_herald` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_herald`;

CREATE TABLE `herald_action` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `action` varchar(255) NOT NULL,
  `target` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruleID` (`ruleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_condition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `fieldName` varchar(255) NOT NULL,
  `fieldCondition` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruleID` (`ruleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentType` varchar(255) NOT NULL,
  `mustMatchAll` tinyint(1) NOT NULL,
  `configVersion` int(10) unsigned NOT NULL DEFAULT '1',
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `repetitionPolicy` int(10) unsigned DEFAULT NULL,
  `ruleType` varchar(255) NOT NULL DEFAULT 'global',
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDisabled` int(10) unsigned NOT NULL DEFAULT '0',
  `triggerObjectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorPHID` (`authorPHID`,`name`),
  UNIQUE KEY `phid` (`phid`),
  KEY `IDX_RULE_TYPE` (`ruleType`),
  KEY `key_trigger` (`triggerObjectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_ruleapplied` (
  `ruleID` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`ruleID`,`phid`),
  KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_ruleedit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `editorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `ruleName` varchar(255) NOT NULL,
  `action` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruleID` (`ruleID`,`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_ruletransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_ruletransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_savedheader` (
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `header` varchar(255) NOT NULL,
  PRIMARY KEY (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `herald_transcript` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `host` varchar(255) NOT NULL,
  `duration` float NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dryRun` tinyint(1) NOT NULL,
  `objectTranscript` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ruleTranscripts` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `conditionTranscripts` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `applyTranscripts` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `garbageCollected` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `objectPHID` (`objectPHID`),
  KEY `garbageCollected` (`garbageCollected`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_maniphest` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_maniphest`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldIndex` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_nameindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `indexedObjectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexedObjectName` varchar(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`indexedObjectPHID`),
  KEY `key_name` (`indexedObjectName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ownerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `ccPHIDs` text,
  `attached` longtext NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `priority` int(10) unsigned NOT NULL,
  `title` text NOT NULL,
  `originalTitle` text NOT NULL,
  `description` longtext NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `projectPHIDs` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mailKey` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ownerOrdering` varchar(64) DEFAULT NULL,
  `originalEmailSource` varchar(255) DEFAULT NULL,
  `subpriority` double NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `priority` (`priority`,`status`),
  KEY `status` (`status`),
  KEY `ownerPHID` (`ownerPHID`,`status`),
  KEY `authorPHID` (`authorPHID`,`status`),
  KEY `ownerOrdering` (`ownerOrdering`),
  KEY `priority_2` (`priority`,`subpriority`),
  KEY `key_dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_taskauxiliarystorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `taskPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `taskPHID` (`taskPHID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_taskproject` (
  `taskPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `projectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`projectPHID`,`taskPHID`),
  UNIQUE KEY `taskPHID` (`taskPHID`,`projectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_tasksubscriber` (
  `taskPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `subscriberPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`subscriberPHID`,`taskPHID`),
  UNIQUE KEY `taskPHID` (`taskPHID`,`subscriberPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `maniphest_transaction_legacy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `taskID` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionType` varchar(16) NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `comments` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `taskID` (`taskID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_meta_data` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_meta_data`;

CREATE TABLE `patch_status` (
  `patch` varchar(255) NOT NULL,
  `applied` int(10) unsigned NOT NULL,
  PRIMARY KEY (`patch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


INSERT INTO `patch_status` VALUES ('phabricator:000.project.sql',1),('phabricator:0000.legacy.sql',1),('phabricator:001.maniphest_projects.sql',1),('phabricator:002.oauth.sql',1),('phabricator:003.more_oauth.sql',1),('phabricator:004.daemonrepos.sql',1),('phabricator:005.workers.sql',1),('phabricator:006.repository.sql',1),('phabricator:007.daemonlog.sql',1),('phabricator:008.repoopt.sql',1),('phabricator:009.repo_summary.sql',1),('phabricator:010.herald.sql',1),('phabricator:011.badcommit.sql',1),('phabricator:012.dropphidtype.sql',1),('phabricator:013.commitdetail.sql',1),('phabricator:014.shortcuts.sql',1),('phabricator:015.preferences.sql',1),('phabricator:016.userrealnameindex.sql',1),('phabricator:017.sessionkeys.sql',1),('phabricator:018.owners.sql',1),('phabricator:019.arcprojects.sql',1),('phabricator:020.pathcapital.sql',1),('phabricator:021.xhpastview.sql',1),('phabricator:022.differentialcommit.sql',1),('phabricator:023.dxkeys.sql',1),('phabricator:024.mlistkeys.sql',1),('phabricator:025.commentopt.sql',1),('phabricator:026.diffpropkey.sql',1),('phabricator:027.metamtakeys.sql',1),('phabricator:028.systemagent.sql',1),('phabricator:029.cursors.sql',1),('phabricator:030.imagemacro.sql',1),('phabricator:031.workerrace.sql',1),('phabricator:032.viewtime.sql',1),('phabricator:033.privtest.sql',1),('phabricator:034.savedheader.sql',1),('phabricator:035.proxyimage.sql',1),('phabricator:036.mailkey.sql',1),('phabricator:037.setuptest.sql',1),('phabricator:038.admin.sql',1),('phabricator:039.userlog.sql',1),('phabricator:040.transform.sql',1),('phabricator:041.heraldrepetition.sql',1),('phabricator:042.commentmetadata.sql',1),('phabricator:043.pastebin.sql',1),('phabricator:044.countdown.sql',1),('phabricator:045.timezone.sql',1),('phabricator:046.conduittoken.sql',1),('phabricator:047.projectstatus.sql',1),('phabricator:048.relationshipkeys.sql',1),('phabricator:049.projectowner.sql',1),('phabricator:050.taskdenormal.sql',1),('phabricator:051.projectfilter.sql',1),('phabricator:052.pastelanguage.sql',1),('phabricator:053.feed.sql',1),('phabricator:054.subscribers.sql',1),('phabricator:055.add_author_to_files.sql',1),('phabricator:056.slowvote.sql',1),('phabricator:057.parsecache.sql',1),('phabricator:058.missingkeys.sql',1),('phabricator:059.engines.php',1),('phabricator:060.phriction.sql',1),('phabricator:061.phrictioncontent.sql',1),('phabricator:062.phrictionmenu.sql',1),('phabricator:063.pasteforks.sql',1),('phabricator:064.subprojects.sql',1),('phabricator:065.sshkeys.sql',1),('phabricator:066.phrictioncontent.sql',1),('phabricator:067.preferences.sql',1),('phabricator:068.maniphestauxiliarystorage.sql',1),('phabricator:069.heraldxscript.sql',1),('phabricator:070.differentialaux.sql',1),('phabricator:071.contentsource.sql',1),('phabricator:072.blamerevert.sql',1),('phabricator:073.reposymbols.sql',1),('phabricator:074.affectedpath.sql',1),('phabricator:075.revisionhash.sql',1),('phabricator:076.indexedlanguages.sql',1),('phabricator:077.originalemail.sql',1),('phabricator:078.nametoken.sql',1),('phabricator:079.nametokenindex.php',1),('phabricator:080.filekeys.sql',1),('phabricator:081.filekeys.php',1),('phabricator:082.xactionkey.sql',1),('phabricator:083.dxviewtime.sql',1),('phabricator:084.pasteauthorkey.sql',1),('phabricator:085.packagecommitrelationship.sql',1),('phabricator:086.formeraffil.sql',1),('phabricator:087.phrictiondelete.sql',1),('phabricator:088.audit.sql',1),('phabricator:089.projectwiki.sql',1),('phabricator:090.forceuniqueprojectnames.php',1),('phabricator:091.uniqueslugkey.sql',1),('phabricator:092.dropgithubnotification.sql',1),('phabricator:093.gitremotes.php',1),('phabricator:094.phrictioncolumn.sql',1),('phabricator:095.directory.sql',1),('phabricator:096.filename.sql',1),('phabricator:097.heraldruletypes.sql',1),('phabricator:098.heraldruletypemigration.php',1),('phabricator:099.drydock.sql',1),('phabricator:100.projectxaction.sql',1),('phabricator:101.heraldruleapplied.sql',1),('phabricator:102.heraldcleanup.php',1),('phabricator:103.heraldedithistory.sql',1),('phabricator:104.searchkey.sql',1),('phabricator:105.mimetype.sql',1),('phabricator:106.chatlog.sql',1),('phabricator:107.oauthserver.sql',1),('phabricator:108.oauthscope.sql',1),('phabricator:109.oauthclientphidkey.sql',1),('phabricator:110.commitaudit.sql',1),('phabricator:111.commitauditmigration.php',1),('phabricator:112.oauthaccesscoderedirecturi.sql',1),('phabricator:113.lastreviewer.sql',1),('phabricator:114.auditrequest.sql',1),('phabricator:115.prepareutf8.sql',1),('phabricator:116.utf8-backup-first-expect-wait.sql',1),('phabricator:117.repositorydescription.php',1),('phabricator:118.auditinline.sql',1),('phabricator:119.filehash.sql',1),('phabricator:120.noop.sql',1),('phabricator:121.drydocklog.sql',1),('phabricator:122.flag.sql',1),('phabricator:123.heraldrulelog.sql',1),('phabricator:124.subpriority.sql',1),('phabricator:125.ipv6.sql',1),('phabricator:126.edges.sql',1),('phabricator:127.userkeybody.sql',1),('phabricator:128.phabricatorcom.sql',1),('phabricator:129.savedquery.sql',1),('phabricator:130.denormalrevisionquery.sql',1),('phabricator:131.migraterevisionquery.php',1),('phabricator:132.phame.sql',1),('phabricator:133.imagemacro.sql',1),('phabricator:134.emptysearch.sql',1),('phabricator:135.datecommitted.sql',1),('phabricator:136.sex.sql',1),('phabricator:137.auditmetadata.sql',1),('phabricator:138.notification.sql',1358377808),('phabricator:20121209.pholioxactions.sql',1358377847),('phabricator:20121209.xmacroadd.sql',1358377849),('phabricator:20121209.xmacromigrate.php',1358377849),('phabricator:20121209.xmacromigratekey.sql',1358377850),('phabricator:20121220.generalcache.sql',1358377851),('phabricator:20121226.config.sql',1358377852),('phabricator:20130101.confxaction.sql',1358377853),('phabricator:20130102.metamtareceivedmailmessageidhash.sql',1358377854),('phabricator:20130103.filemetadata.sql',1358377855),('phabricator:20130111.conpherence.sql',1390001562),('phabricator:20130127.altheraldtranscript.sql',1390001562),('phabricator:20130131.conpherencepics.sql',1390001562),('phabricator:20130201.revisionunsubscribed.php',1390001562),('phabricator:20130201.revisionunsubscribed.sql',1390001562),('phabricator:20130214.chatlogchannel.sql',1390001562),('phabricator:20130214.chatlogchannelid.sql',1390001562),('phabricator:20130214.token.sql',1390001562),('phabricator:20130215.phabricatorfileaddttl.sql',1390001562),('phabricator:20130217.cachettl.sql',1390001562),('phabricator:20130218.longdaemon.sql',1390001562),('phabricator:20130218.updatechannelid.php',1390001562),('phabricator:20130219.commitsummary.sql',1390001562),('phabricator:20130219.commitsummarymig.php',1390001562),('phabricator:20130222.dropchannel.sql',1390001562),('phabricator:20130226.commitkey.sql',1390001562),('phabricator:20130304.lintauthor.sql',1390001562),('phabricator:20130310.xactionmeta.sql',1390001562),('phabricator:20130317.phrictionedge.sql',1390001562),('phabricator:20130319.conpherence.sql',1390001562),('phabricator:20130319.phabricatorfileexplicitupload.sql',1390001562),('phabricator:20130320.phlux.sql',1390001562),('phabricator:20130321.token.sql',1390001562),('phabricator:20130322.phortune.sql',1390001562),('phabricator:20130323.phortunepayment.sql',1390001562),('phabricator:20130324.phortuneproduct.sql',1390001562),('phabricator:20130330.phrequent.sql',1390001562),('phabricator:20130403.conpherencecache.sql',1390001562),('phabricator:20130403.conpherencecachemig.php',1390001562),('phabricator:20130409.commitdrev.php',1390001562),('phabricator:20130417.externalaccount.sql',1390001562),('phabricator:20130423.conpherenceindices.sql',1390001562),('phabricator:20130423.phortunepaymentrevised.sql',1390001562),('phabricator:20130423.updateexternalaccount.sql',1390001562),('phabricator:20130426.search_savedquery.sql',1390001562),('phabricator:20130502.countdownrevamp1.sql',1390001562),('phabricator:20130502.countdownrevamp2.php',1390001562),('phabricator:20130502.countdownrevamp3.sql',1390001562),('phabricator:20130507.releephrqmailkey.sql',1390001562),('phabricator:20130507.releephrqmailkeypop.php',1390001562),('phabricator:20130507.releephrqsimplifycols.sql',1390001562),('phabricator:20130508.releephtransactions.sql',1390001562),('phabricator:20130508.releephtransactionsmig.php',1390001562),('phabricator:20130508.search_namedquery.sql',1390001562),('phabricator:20130513.receviedmailstatus.sql',1390001562),('phabricator:20130519.diviner.sql',1390001563),('phabricator:20130521.dropconphimages.sql',1390001563),('phabricator:20130523.maniphest_owners.sql',1390001563),('phabricator:20130524.repoxactions.sql',1390001563),('phabricator:20130529.macroauthor.sql',1390001563),('phabricator:20130529.macroauthormig.php',1390001563),('phabricator:20130530.macrodatekey.sql',1390001563),('phabricator:20130530.pastekeys.sql',1390001563),('phabricator:20130530.sessionhash.php',1390001563),('phabricator:20130531.filekeys.sql',1390001563),('phabricator:20130602.morediviner.sql',1390001563),('phabricator:20130602.namedqueries.sql',1390001563),('phabricator:20130606.userxactions.sql',1390001563),('phabricator:20130607.xaccount.sql',1390001563),('phabricator:20130611.migrateoauth.php',1390001563),('phabricator:20130611.nukeldap.php',1390001563),('phabricator:20130613.authdb.sql',1390001563),('phabricator:20130619.authconf.php',1390001563),('phabricator:20130620.diffxactions.sql',1390001563),('phabricator:20130621.diffcommentphid.sql',1390001563),('phabricator:20130621.diffcommentphidmig.php',1390001563),('phabricator:20130621.diffcommentunphid.sql',1390001563),('phabricator:20130622.doorkeeper.sql',1390001563),('phabricator:20130628.legalpadv0.sql',1390001563),('phabricator:20130701.conduitlog.sql',1390001563),('phabricator:20130703.legalpaddocdenorm.php',1390001563),('phabricator:20130703.legalpaddocdenorm.sql',1390001563),('phabricator:20130709.droptimeline.sql',1390001563),('phabricator:20130709.legalpadsignature.sql',1390001563),('phabricator:20130711.pholioimageobsolete.php',1390001563),('phabricator:20130711.pholioimageobsolete.sql',1390001563),('phabricator:20130711.pholioimageobsolete2.sql',1390001563),('phabricator:20130711.trimrealnames.php',1390001563),('phabricator:20130714.votexactions.sql',1390001563),('phabricator:20130715.votecomments.php',1390001563),('phabricator:20130715.voteedges.sql',1390001563),('phabricator:20130716.archivememberlessprojects.php',1390001563),('phabricator:20130722.pholioreplace.sql',1390001563),('phabricator:20130723.taskstarttime.sql',1390001563),('phabricator:20130726.ponderxactions.sql',1390001564),('phabricator:20130727.ponderquestionstatus.sql',1390001563),('phabricator:20130728.ponderunique.php',1390001564),('phabricator:20130728.ponderuniquekey.sql',1390001564),('phabricator:20130728.ponderxcomment.php',1390001564),('phabricator:20130731.releephcutpointidentifier.sql',1390001564),('phabricator:20130731.releephproject.sql',1390001564),('phabricator:20130731.releephrepoid.sql',1390001564),('phabricator:20130801.pastexactions.php',1390001564),('phabricator:20130801.pastexactions.sql',1390001564),('phabricator:20130802.heraldphid.sql',1390001564),('phabricator:20130802.heraldphids.php',1390001564),('phabricator:20130802.heraldphidukey.sql',1390001564),('phabricator:20130802.heraldxactions.sql',1390001564),('phabricator:20130805.pasteedges.sql',1390001564),('phabricator:20130805.pastemailkey.sql',1390001564),('phabricator:20130805.pastemailkeypop.php',1390001564),('phabricator:20130814.usercustom.sql',1390001564),('phabricator:20130820.file-mailkey-populate.php',1390001564),('phabricator:20130820.filemailkey.sql',1390001564),('phabricator:20130820.filexactions.sql',1390001564),('phabricator:20130820.releephxactions.sql',1390001564),('phabricator:20130826.divinernode.sql',1390001564),('phabricator:20130912.maniphest.1.touch.sql',1390001564),('phabricator:20130912.maniphest.2.created.sql',1390001564),('phabricator:20130912.maniphest.3.nameindex.sql',1390001564),('phabricator:20130912.maniphest.4.fillindex.php',1390001564),('phabricator:20130913.maniphest.1.migratesearch.php',1390001564),('phabricator:20130914.usercustom.sql',1390001564),('phabricator:20130915.maniphestcustom.sql',1390001564),('phabricator:20130915.maniphestmigrate.php',1390001564),('phabricator:20130915.maniphestqdrop.sql',1390001565),('phabricator:20130919.mfieldconf.php',1390001564),('phabricator:20130920.repokeyspolicy.sql',1390001564),('phabricator:20130921.mtransactions.sql',1390001564),('phabricator:20130921.xmigratemaniphest.php',1390001564),('phabricator:20130923.mrename.sql',1390001564),('phabricator:20130924.mdraftkey.sql',1390001564),('phabricator:20130925.mpolicy.sql',1390001564),('phabricator:20130925.xpolicy.sql',1390001564),('phabricator:20130926.dcustom.sql',1390001564),('phabricator:20130926.dinkeys.sql',1390001565),('phabricator:20130926.dinline.php',1390001565),('phabricator:20130927.audiomacro.sql',1390001565),('phabricator:20130929.filepolicy.sql',1390001565),('phabricator:20131004.dxedgekey.sql',1390001565),('phabricator:20131004.dxreviewers.php',1390001565),('phabricator:20131006.hdisable.sql',1390001565),('phabricator:20131010.pstorage.sql',1390001565),('phabricator:20131015.cpolicy.sql',1390001565),('phabricator:20131020.col1.sql',1390001565),('phabricator:20131020.harbormaster.sql',1390001565),('phabricator:20131020.pcustom.sql',1390001565),('phabricator:20131020.pxaction.sql',1390001565),('phabricator:20131020.pxactionmig.php',1390001565),('phabricator:20131025.repopush.sql',1390001565),('phabricator:20131026.commitstatus.sql',1390001565),('phabricator:20131030.repostatusmessage.sql',1390001565),('phabricator:20131031.vcspassword.sql',1390001565),('phabricator:20131105.buildstep.sql',1390001565),('phabricator:20131106.diffphid.1.col.sql',1390001565),('phabricator:20131106.diffphid.2.mig.php',1390001565),('phabricator:20131106.diffphid.3.key.sql',1390001565),('phabricator:20131106.nuance-v0.sql',1390001565),('phabricator:20131107.buildlog.sql',1390001565),('phabricator:20131112.userverified.1.col.sql',1390001565),('phabricator:20131112.userverified.2.mig.php',1390001565),('phabricator:20131118.ownerorder.php',1390001565),('phabricator:20131119.passphrase.sql',1390001565),('phabricator:20131120.nuancesourcetype.sql',1390001565),('phabricator:20131121.passphraseedge.sql',1390001565),('phabricator:20131121.repocredentials.1.col.sql',1390001565),('phabricator:20131121.repocredentials.2.mig.php',1390001565),('phabricator:20131122.repomirror.sql',1390001565),('phabricator:20131123.drydockblueprintpolicy.sql',1390001565),('phabricator:20131129.drydockresourceblueprint.sql',1390001565),('phabricator:20131204.pushlog.sql',1390001565),('phabricator:20131205.buildsteporder.sql',1390001565),('phabricator:20131205.buildstepordermig.php',1390001565),('phabricator:20131205.buildtargets.sql',1390001565),('phabricator:20131206.phragment.sql',1390001565),('phabricator:20131206.phragmentnull.sql',1390001565),('phabricator:20131208.phragmentsnapshot.sql',1390001565),('phabricator:20131211.phragmentedges.sql',1390001565),('phabricator:20131217.pushlogphid.1.col.sql',1390001565),('phabricator:20131217.pushlogphid.2.mig.php',1390001565),('phabricator:20131217.pushlogphid.3.key.sql',1390001565),('phabricator:20131219.pxdrop.sql',1390001565),('phabricator:20131224.harbormanual.sql',1390001566),('phabricator:20131227.heraldobject.sql',1390001566),('phabricator:20131231.dropshortcut.sql',1390001566),('phabricator:20131302.maniphestvalue.sql',1390001562),('phabricator:20140104.harbormastercmd.sql',1390001566),('phabricator:20140106.macromailkey.1.sql',1390001566),('phabricator:20140106.macromailkey.2.php',1390001566),('phabricator:20140108.ddbpname.1.sql',1390001566),('phabricator:20140108.ddbpname.2.php',1390001566),('phabricator:20140109.ddxactions.sql',1390001566),('phabricator:20140109.projectcolumnsdates.sql',1390001566),('phabricator:20140113.legalpadsig.1.sql',1390001566),('phabricator:20140113.legalpadsig.2.php',1390001566),('phabricator:20140115.auth.1.id.sql',1390001566),('phabricator:20140115.auth.2.expires.sql',1390001566),('phabricator:20140115.auth.3.unlimit.php',1390001566),('phabricator:20140115.legalpadsigkey.sql',1390001566),('phabricator:20140116.reporefcursor.sql',1390001566),('phabricator:daemonstatus.sql',1358377823),('phabricator:daemonstatuskey.sql',1358377828),('phabricator:daemontaskarchive.sql',1358377838),('phabricator:db.audit',1),('phabricator:db.auth',1390001562),('phabricator:db.cache',1358377804),('phabricator:db.calendar',1358377802),('phabricator:db.chatlog',1),('phabricator:db.conduit',1),('phabricator:db.config',1358377852),('phabricator:db.conpherence',1390001562),('phabricator:db.countdown',1),('phabricator:db.daemon',1),('phabricator:db.differential',1),('phabricator:db.diviner',1390001562),('phabricator:db.doorkeeper',1390001562),('phabricator:db.draft',1),('phabricator:db.drydock',1),('phabricator:db.fact',1358377804),('phabricator:db.feed',1),('phabricator:db.file',1),('phabricator:db.flag',1),('phabricator:db.harbormaster',1358377803),('phabricator:db.herald',1),('phabricator:db.legalpad',1390001562),('phabricator:db.maniphest',1),('phabricator:db.metamta',1),('phabricator:db.meta_data',1),('phabricator:db.nuance',1390001562),('phabricator:db.oauth_server',1),('phabricator:db.owners',1),('phabricator:db.passphrase',1390001562),('phabricator:db.pastebin',1),('phabricator:db.phame',1),('phabricator:db.phid',1),('phabricator:db.phlux',1390001562),('phabricator:db.pholio',1358377807),('phabricator:db.phortune',1390001562),('phabricator:db.phragment',1390001562),('phabricator:db.phrequent',1390001562),('phabricator:db.phriction',1),('phabricator:db.policy',1390001562),('phabricator:db.ponder',1358377805),('phabricator:db.project',1),('phabricator:db.releeph',1390001562),('phabricator:db.repository',1),('phabricator:db.search',1),('phabricator:db.slowvote',1),('phabricator:db.timeline',1),('phabricator:db.token',1390001562),('phabricator:db.user',1),('phabricator:db.worker',1),('phabricator:db.xhpastview',1),('phabricator:db.xhprof',1358377806),('phabricator:differentialbookmarks.sql',1358377817),('phabricator:draft-metadata.sql',1358377832),('phabricator:dropfileproxyimage.sql',1358377843),('phabricator:drydockresoucetype.sql',1358377840),('phabricator:drydocktaskid.sql',1358377839),('phabricator:edgetype.sql',1358377829),('phabricator:emailtable.sql',1358377810),('phabricator:emailtableport.sql',1358377811),('phabricator:emailtableremove.sql',1358377811),('phabricator:fact-raw.sql',1358377825),('phabricator:harbormasterobject.sql',1358377817),('phabricator:holidays.sql',1358377808),('phabricator:ldapinfo.sql',1358377814),('phabricator:legalpad-mailkey-populate.php',1390001563),('phabricator:legalpad-mailkey.sql',1390001563),('phabricator:liskcounters-task.sql',1358377844),('phabricator:liskcounters.php',1358377842),('phabricator:liskcounters.sql',1358377841),('phabricator:maniphestxcache.sql',1358377819),('phabricator:markupcache.sql',1358377818),('phabricator:migrate-differential-dependencies.php',1358377820),('phabricator:migrate-maniphest-dependencies.php',1358377820),('phabricator:migrate-maniphest-revisions.php',1358377822),('phabricator:migrate-project-edges.php',1358377824),('phabricator:owners-exclude.sql',1358377846),('phabricator:pastepolicy.sql',1358377831),('phabricator:phameblog.sql',1358377821),('phabricator:phamedomain.sql',1358377833),('phabricator:phameoneblog.sql',1358377837),('phabricator:phamepolicy.sql',1358377836),('phabricator:phiddrop.sql',1358377812),('phabricator:pholio.sql',1358377846),('phabricator:policy-project.sql',1358377827),('phabricator:ponder-comments.sql',1358377830),('phabricator:ponder-mailkey-populate.php',1358377835),('phabricator:ponder-mailkey.sql',1358377834),('phabricator:ponder.sql',1358377826),('phabricator:releeph.sql',1390001562),('phabricator:repository-lint.sql',1358377843),('phabricator:statustxt.sql',1358377837),('phabricator:symbolcontexts.sql',1358377823),('phabricator:testdatabase.sql',1358377813),('phabricator:threadtopic.sql',1358377815),('phabricator:userstatus.sql',1358377809),('phabricator:usertranslation.sql',1358377816),('phabricator:xhprof.sql',1358377832);

CREATE DATABASE `{$NAMESPACE}_metamta` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_metamta`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `metamta_mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parameters` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `status` varchar(255) NOT NULL,
  `message` text,
  `retryCount` int(10) unsigned NOT NULL,
  `nextRetry` int(10) unsigned NOT NULL,
  `relatedPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`,`nextRetry`),
  KEY `relatedPHID` (`relatedPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `metamta_mailinglist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `uri` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `metamta_receivedmail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `headers` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `bodies` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attachments` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `relatedPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `message` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `messageIDHash` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `status` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `relatedPHID` (`relatedPHID`),
  KEY `authorPHID` (`authorPHID`),
  KEY `key_messageIDHash` (`messageIDHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_oauth_server` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_oauth_server`;

CREATE TABLE `oauth_server_oauthclientauthorization` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `clientPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `scope` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `userPHID` (`userPHID`,`clientPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `oauth_server_oauthserveraccesstoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(32) NOT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `clientPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `oauth_server_oauthserverauthorizationcode` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `clientPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `clientSecret` varchar(32) NOT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `redirectURI` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `oauth_server_oauthserverclient` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `secret` varchar(32) NOT NULL,
  `redirectURI` varchar(255) NOT NULL,
  `creatorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `creatorPHID` (`creatorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_owners` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_owners`;

CREATE TABLE `owners_owner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packageID` int(10) unsigned NOT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `packageID` (`packageID`,`userPHID`),
  KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `owners_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `originalName` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `primaryOwnerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `auditingEnabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `owners_path` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packageID` int(10) unsigned NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `path` varchar(255) NOT NULL,
  `excluded` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `packageID` (`packageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_pastebin` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_pastebin`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pastebin_paste` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `filePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `language` varchar(64) NOT NULL,
  `parentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `mailKey` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parentPHID` (`parentPHID`),
  KEY `authorPHID` (`authorPHID`),
  KEY `key_dateCreated` (`dateCreated`),
  KEY `key_language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pastebin_pastetransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pastebin_pastetransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `lineNumber` int(10) unsigned DEFAULT NULL,
  `lineLength` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_phame` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_phame`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phame_blog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `description` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `configData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `creatorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `joinPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phame_post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `bloggerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title` varchar(255) NOT NULL,
  `phameTitle` varchar(64) NOT NULL,
  `body` longtext,
  `visibility` int(10) unsigned NOT NULL DEFAULT '0',
  `configData` longtext,
  `datePublished` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `blogPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `phameTitle` (`bloggerPHID`,`phameTitle`),
  KEY `bloggerPosts` (`bloggerPHID`,`visibility`,`datePublished`,`id`),
  KEY `instancePosts` (`visibility`,`datePublished`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_phriction` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_phriction`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phriction_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentID` int(10) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title` varchar(512) NOT NULL,
  `slug` varchar(512) NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` varchar(512) DEFAULT NULL,
  `changeType` int(10) unsigned NOT NULL DEFAULT '0',
  `changeRef` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documentID` (`documentID`,`version`),
  KEY `authorPHID` (`authorPHID`),
  KEY `slug` (`slug`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phriction_document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `slug` varchar(128) NOT NULL,
  `depth` int(10) unsigned NOT NULL,
  `contentID` int(10) unsigned DEFAULT NULL,
  `status` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `depth` (`depth`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_project` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_project`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(32) NOT NULL,
  `subprojectPHIDs` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `phrictionSlug` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `joinPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `phrictionSlug` (`phrictionSlug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_affiliation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `role` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isOwner` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectPHID` (`projectPHID`,`userPHID`),
  KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_column` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `projectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_sequence` (`projectPHID`,`sequence`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldIndex` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `blurb` longtext NOT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectPHID` (`projectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_subproject` (
  `projectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `subprojectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`subprojectPHID`,`projectPHID`),
  UNIQUE KEY `projectPHID` (`projectPHID`,`subprojectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `project_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_repository` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_repository`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `callsign` varchar(32) NOT NULL,
  `versionControlSystem` varchar(32) NOT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `uuid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `pushPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `credentialPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `callsign` (`callsign`),
  UNIQUE KEY `phid` (`phid`),
  KEY `key_name` (`name`),
  KEY `key_vcs` (`versionControlSystem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_arcanistproject` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `repositoryID` int(10) unsigned DEFAULT NULL,
  `symbolIndexLanguages` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `symbolIndexProjects` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_auditrequest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auditorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `auditStatus` varchar(64) NOT NULL,
  `auditReasons` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commitPHID` (`commitPHID`),
  KEY `auditorPHID` (`auditorPHID`,`auditStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_badcommit` (
  `fullCommitName` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `description` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`fullCommitName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_branch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `lintCommit` varchar(40) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repositoryID` (`repositoryID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_commit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commitIdentifier` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `mailKey` varchar(20) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `auditStatus` int(10) unsigned NOT NULL,
  `summary` varchar(80) NOT NULL,
  `importStatus` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_commit_identity` (`commitIdentifier`,`repositoryID`),
  KEY `repositoryID_2` (`repositoryID`,`epoch`),
  KEY `authorPHID` (`authorPHID`,`auditStatus`,`epoch`),
  KEY `repositoryID` (`repositoryID`,`importStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_commitdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commitID` int(10) unsigned NOT NULL,
  `authorName` varchar(255) NOT NULL,
  `commitMessage` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commitDetails` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commitID` (`commitID`),
  KEY `authorName` (`authorName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_filesystem` (
  `repositoryID` int(10) unsigned NOT NULL,
  `parentID` int(10) unsigned NOT NULL,
  `svnCommit` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `existed` tinyint(1) NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  PRIMARY KEY (`repositoryID`,`parentID`,`pathID`,`svnCommit`),
  KEY `repositoryID` (`repositoryID`,`svnCommit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_lintmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branchID` int(10) unsigned NOT NULL,
  `path` varchar(512) NOT NULL,
  `line` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `code` varchar(32) NOT NULL,
  `severity` varchar(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `branchID` (`branchID`,`path`(64)),
  KEY `branchID_2` (`branchID`,`code`,`path`(64)),
  KEY `key_author` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_mirror` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `remoteURI` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `credentialPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_repository` (`repositoryPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_path` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(512) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `pathHash` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pathHash` (`pathHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_pathchange` (
  `repositoryID` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `commitID` int(10) unsigned NOT NULL,
  `targetPathID` int(10) unsigned DEFAULT NULL,
  `targetCommitID` int(10) unsigned DEFAULT NULL,
  `changeType` int(10) unsigned NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  `isDirect` tinyint(1) NOT NULL,
  `commitSequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`commitID`,`pathID`),
  KEY `repositoryID` (`repositoryID`,`pathID`,`commitSequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_pushlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `pusherPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `remoteAddress` int(10) unsigned DEFAULT NULL,
  `remoteProtocol` varchar(32) DEFAULT NULL,
  `transactionKey` char(12) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `refType` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `refNameHash` varchar(12) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `refNameRaw` longtext CHARACTER SET latin1 COLLATE latin1_bin,
  `refNameEncoding` varchar(16) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `refOld` varchar(40) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `refNew` varchar(40) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `mergeBase` varchar(40) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `changeFlags` int(10) unsigned NOT NULL,
  `rejectCode` int(10) unsigned NOT NULL,
  `rejectDetails` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_repository` (`repositoryPHID`),
  KEY `key_ref` (`repositoryPHID`,`refNew`),
  KEY `key_pusher` (`pusherPHID`),
  KEY `key_name` (`repositoryPHID`,`refNameHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_refcursor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `refType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `refNameHash` varchar(12) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `refNameRaw` longtext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `refNameEncoding` varchar(16) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commitIdentifier` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_cursor` (`repositoryPHID`,`refType`,`refNameHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_statusmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `statusType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `statusCode` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `parameters` longtext NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repositoryID` (`repositoryID`,`statusType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_summary` (
  `repositoryID` int(10) unsigned NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `lastCommitID` int(10) unsigned NOT NULL,
  `epoch` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`repositoryID`),
  KEY `key_epoch` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_symbol` (
  `arcanistProjectID` int(10) unsigned NOT NULL,
  `symbolContext` varchar(128) NOT NULL DEFAULT '',
  `symbolName` varchar(128) NOT NULL,
  `symbolType` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `symbolLanguage` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  KEY `symbolName` (`symbolName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `repository_vcspassword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `passwordHash` varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_search` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_search`;

CREATE TABLE `search_document` (
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `documentType` varchar(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `documentTitle` varchar(255) NOT NULL,
  `documentCreated` int(10) unsigned NOT NULL,
  `documentModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`phid`),
  KEY `documentCreated` (`documentCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `search_documentfield` (
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `phidType` varchar(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `field` varchar(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `auxPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `corpus` text,
  KEY `phid` (`phid`),
  FULLTEXT KEY `corpus` (`corpus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `search_documentrelationship` (
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `relatedPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `relation` varchar(4) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `relatedType` varchar(4) NOT NULL,
  `relatedTime` int(10) unsigned NOT NULL,
  KEY `phid` (`phid`),
  KEY `relatedPHID` (`relatedPHID`,`relation`),
  KEY `relation` (`relation`,`relatedPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `search_namedquery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `engineClassName` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `queryName` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `queryKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isBuiltin` tinyint(1) NOT NULL DEFAULT '0',
  `isDisabled` tinyint(1) NOT NULL DEFAULT '0',
  `sequence` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_userquery` (`userPHID`,`engineClassName`,`queryKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `search_query` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `query` varchar(255) NOT NULL,
  `parameters` text NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `queryKey` varchar(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `queryKey` (`queryKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `search_savedquery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `engineClassName` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `parameters` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `queryKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_queryKey` (`queryKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_slowvote` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_slowvote`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `slowvote_choice` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pollID` int(10) unsigned NOT NULL,
  `optionID` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pollID` (`pollID`),
  KEY `authorPHID` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `slowvote_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pollID` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentText` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pollID` (`pollID`,`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `slowvote_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pollID` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pollID` (`pollID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `slowvote_poll` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `responseVisibility` int(10) unsigned NOT NULL,
  `shuffle` int(10) unsigned NOT NULL,
  `method` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `slowvote_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `slowvote_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_user` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_user`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phabricator_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sessionKey` varchar(40) NOT NULL,
  `sessionStart` int(10) unsigned NOT NULL,
  `sessionExpires` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sessionKey` (`sessionKey`),
  KEY `key_identity` (`userPHID`,`type`),
  KEY `key_expires` (`sessionExpires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `userName` varchar(64) NOT NULL,
  `realName` varchar(128) NOT NULL,
  `sex` char(1) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `translation` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `passwordSalt` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `passwordHash` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `consoleEnabled` tinyint(1) NOT NULL,
  `consoleVisible` tinyint(1) NOT NULL,
  `consoleTab` varchar(64) NOT NULL,
  `conduitCertificate` varchar(255) NOT NULL,
  `isSystemAgent` tinyint(1) NOT NULL DEFAULT '0',
  `isDisabled` tinyint(1) NOT NULL,
  `isAdmin` tinyint(1) NOT NULL,
  `timezoneIdentifier` varchar(255) NOT NULL,
  `isEmailVerified` int(10) unsigned NOT NULL,
  `isApproved` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userName` (`userName`),
  UNIQUE KEY `phid` (`phid`),
  KEY `realName` (`realName`),
  KEY `key_approved` (`isApproved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_configuredcustomfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldIndex` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fieldValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexKey` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `indexValue` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_email` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `address` varchar(128) NOT NULL,
  `isVerified` tinyint(1) NOT NULL DEFAULT '0',
  `isPrimary` tinyint(1) NOT NULL DEFAULT '0',
  `verificationCode` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  KEY `userPHID` (`userPHID`,`isPrimary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_externalaccount` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `accountType` varchar(16) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `accountDomain` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `accountSecret` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `accountID` varchar(160) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `displayName` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `username` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `realName` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `emailVerified` tinyint(1) NOT NULL,
  `accountURI` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `account_details` (`accountType`,`accountDomain`,`accountID`),
  KEY `key_userAccounts` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_ldapinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userID` int(10) unsigned NOT NULL,
  `ldapUsername` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `action` varchar(64) NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `remoteAddr` varchar(45) NOT NULL,
  `session` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `actorPHID` (`actorPHID`,`dateCreated`),
  KEY `userPHID` (`userPHID`,`dateCreated`),
  KEY `action` (`action`,`dateCreated`),
  KEY `dateCreated` (`dateCreated`),
  KEY `remoteAddr` (`remoteAddr`,`dateCreated`),
  KEY `session` (`session`,`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_nametoken` (
  `token` varchar(255) NOT NULL,
  `userID` int(10) unsigned NOT NULL,
  KEY `token` (`token`),
  KEY `userID` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_oauthinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userID` int(10) unsigned NOT NULL,
  `oauthProvider` varchar(255) NOT NULL,
  `oauthUID` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `accountURI` varchar(255) DEFAULT NULL,
  `accountName` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `tokenExpires` int(10) unsigned DEFAULT NULL,
  `tokenScope` varchar(255) DEFAULT NULL,
  `tokenStatus` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID` (`userID`,`oauthProvider`),
  UNIQUE KEY `oauthProvider` (`oauthProvider`,`oauthUID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `preferences` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title` varchar(255) NOT NULL,
  `blurb` text NOT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_sshkey` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `keyType` varchar(255) DEFAULT NULL,
  `keyBody` text CHARACTER SET utf8 COLLATE utf8_bin,
  `keyHash` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `keyComment` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyHash` (`keyHash`),
  KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) NOT NULL,
  `dateFrom` int(10) unsigned NOT NULL,
  `dateTo` int(10) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userPHID_dateFrom` (`userPHID`,`dateTo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `user_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_worker` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_worker`;

CREATE TABLE `lisk_counter` (
  `counterName` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `counterValue` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`counterName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


INSERT INTO `lisk_counter` VALUES ('worker_activetask',2);

CREATE TABLE `worker_activetask` (
  `id` int(10) unsigned NOT NULL,
  `taskClass` varchar(255) NOT NULL,
  `leaseOwner` varchar(255) DEFAULT NULL,
  `leaseExpires` int(10) unsigned DEFAULT NULL,
  `failureCount` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  `failureTime` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dataID` (`dataID`),
  KEY `taskClass` (`taskClass`),
  KEY `leaseExpires` (`leaseExpires`),
  KEY `leaseOwner` (`leaseOwner`(16)),
  KEY `key_failuretime` (`failureTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `worker_archivetask` (
  `id` int(10) unsigned NOT NULL,
  `taskClass` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `leaseOwner` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `leaseExpires` int(10) unsigned DEFAULT NULL,
  `failureCount` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned NOT NULL,
  `result` int(10) unsigned NOT NULL,
  `duration` bigint(20) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `worker_taskdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_xhpastview` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_xhpastview`;

CREATE TABLE `xhpastview_parsetree` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `input` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `stdout` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_cache` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_cache`;

CREATE TABLE `cache_general` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cacheKeyHash` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cacheKey` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cacheFormat` varchar(16) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cacheData` longblob NOT NULL,
  `cacheCreated` int(10) unsigned NOT NULL,
  `cacheExpires` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_cacheKeyHash` (`cacheKeyHash`),
  KEY `key_cacheCreated` (`cacheCreated`),
  KEY `key_ttl` (`cacheExpires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `cache_markupcache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cacheKey` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cacheData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cacheKey` (`cacheKey`),
  KEY `dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_fact` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_fact`;

CREATE TABLE `fact_aggregate` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `factType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `valueX` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `factType` (`factType`,`objectPHID`),
  KEY `factType_2` (`factType`,`valueX`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fact_cursor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `position` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `fact_raw` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `factType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectA` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `valueX` bigint(20) NOT NULL,
  `valueY` bigint(20) NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `objectPHID` (`objectPHID`),
  KEY `factType` (`factType`,`epoch`),
  KEY `factType_2` (`factType`,`objectA`,`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_ponder` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_ponder`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_answer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `questionID` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `voteCount` int(10) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `content` longtext NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `contentSource` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_oneanswerperquestion` (`questionID`,`authorPHID`),
  KEY `questionID` (`questionID`),
  KEY `authorPHID` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_answertransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_answertransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `targetPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `content` longtext NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `authorPHID` (`authorPHID`),
  KEY `targetPHID` (`targetPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_question` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `voteCount` int(10) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `content` longtext NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `contentSource` varchar(255) DEFAULT NULL,
  `heat` float NOT NULL,
  `answerCount` int(10) unsigned NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`),
  KEY `heat` (`heat`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_questiontransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `ponder_questiontransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_xhprof` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_xhprof`;

CREATE TABLE `xhprof_sample` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sampleRate` int(11) NOT NULL,
  `usTotal` bigint(20) unsigned NOT NULL,
  `hostname` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `requestPath` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `controller` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` bigint(20) unsigned NOT NULL,
  `dateModified` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filePHID` (`filePHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_pholio` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_pholio`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pholio_image` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mockID` int(10) unsigned DEFAULT NULL,
  `filePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` longtext NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isObsolete` tinyint(1) NOT NULL DEFAULT '0',
  `replacesImagePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyPHID` (`phid`),
  KEY `mockID` (`mockID`,`isObsolete`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pholio_mock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(128) NOT NULL,
  `originalName` varchar(128) NOT NULL,
  `description` longtext NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `coverPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pholio_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `pholio_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `mockID` int(10) unsigned DEFAULT NULL,
  `imageID` int(10) unsigned DEFAULT NULL,
  `x` int(10) unsigned DEFAULT NULL,
  `y` int(10) unsigned DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`mockID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_conpherence` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_conpherence`;

CREATE TABLE `conpherence_participant` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `participantPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `conpherencePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `participationStatus` int(10) unsigned NOT NULL DEFAULT '0',
  `dateTouched` int(10) unsigned NOT NULL,
  `behindTransactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `seenMessageCount` bigint(20) unsigned NOT NULL,
  `settings` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conpherencePHID` (`conpherencePHID`,`participantPHID`),
  KEY `unreadCount` (`participantPHID`,`participationStatus`),
  KEY `participationIndex` (`participantPHID`,`dateTouched`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `conpherence_thread` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `messageCount` bigint(20) unsigned NOT NULL,
  `recentParticipantPHIDs` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `conpherence_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `conpherence_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `conpherencePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`conpherencePHID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_config` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_config`;

CREATE TABLE `config_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `namespace` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `configKey` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` longtext NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`namespace`,`configKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `config_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_token` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_token`;

CREATE TABLE `token_count` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `tokenCount` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_objectPHID` (`objectPHID`),
  KEY `key_count` (`tokenCount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `token_given` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `tokenPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_all` (`objectPHID`,`authorPHID`),
  KEY `key_author` (`authorPHID`),
  KEY `key_token` (`tokenPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_releeph` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_releeph`;

CREATE TABLE `releeph_branch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `basename` varchar(64) NOT NULL,
  `releephProjectID` int(10) unsigned NOT NULL,
  `createdByUserPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cutPointCommitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `symbolicName` varchar(64) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `releephProjectID_2` (`releephProjectID`,`basename`),
  UNIQUE KEY `releephProjectID_name` (`releephProjectID`,`name`),
  UNIQUE KEY `releephProjectID` (`releephProjectID`,`symbolicName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_branchtransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `releephProjectID` int(10) unsigned NOT NULL,
  `releephBranchID` int(10) unsigned DEFAULT NULL,
  `type` varchar(32) NOT NULL,
  `epoch` int(10) unsigned DEFAULT NULL,
  `actorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `trunkBranch` varchar(255) NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `arcanistProjectID` int(10) unsigned NOT NULL,
  `createdByUserPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectName` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_projecttransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_request` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `branchID` int(10) unsigned NOT NULL,
  `summary` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `requestUserPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `requestCommitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commitIdentifier` varchar(40) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `pickStatus` tinyint(4) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `userIntents` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `inBranch` tinyint(1) NOT NULL DEFAULT '0',
  `mailKey` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `requestIdentifierBranch` (`requestCommitPHID`,`branchID`),
  KEY `branchID` (`branchID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_requestevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `releephRequestID` int(10) unsigned NOT NULL,
  `actorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_requesttransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `releeph_requesttransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_phlux` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_phlux`;

CREATE TABLE `phlux_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phlux_variable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `variableKey` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `variableValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_key` (`variableKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_phortune` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_phortune`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phortune_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `balanceInCents` bigint(20) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phortune_accounttransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phortune_paymentmethod` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `accountPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `brand` varchar(64) NOT NULL,
  `expires` varchar(16) NOT NULL,
  `providerType` varchar(16) NOT NULL,
  `providerDomain` varchar(64) NOT NULL,
  `lastFourDigits` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_account` (`accountPHID`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phortune_product` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `productName` varchar(255) NOT NULL,
  `productType` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `status` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `priceInCents` bigint(20) NOT NULL,
  `billingIntervalInMonths` int(10) unsigned DEFAULT NULL,
  `trialPeriodInDays` int(10) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phortune_producttransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_phrequent` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_phrequent`;

CREATE TABLE `phrequent_usertime` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `note` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `dateStarted` int(10) unsigned NOT NULL,
  `dateEnded` int(10) unsigned DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_diviner` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_diviner`;

CREATE TABLE `diviner_liveatom` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `symbolPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `atomData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbolPHID` (`symbolPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `diviner_livebook` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `configurationData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `diviner_livesymbol` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `bookPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `context` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `type` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `atomIndex` int(10) unsigned NOT NULL,
  `identityHash` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `graphHash` varchar(33) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `groupName` varchar(255) DEFAULT NULL,
  `summary` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `isDocumentable` tinyint(1) NOT NULL,
  `nodeHash` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identityHash` (`identityHash`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `graphHash` (`graphHash`),
  UNIQUE KEY `nodeHash` (`nodeHash`),
  KEY `bookPHID` (`bookPHID`,`type`,`name`(64),`context`(64),`atomIndex`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_auth` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_auth`;

CREATE TABLE `auth_providerconfig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `providerClass` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `providerType` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `providerDomain` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isEnabled` tinyint(1) NOT NULL,
  `shouldAllowLogin` tinyint(1) NOT NULL,
  `shouldAllowRegistration` tinyint(1) NOT NULL,
  `shouldAllowLink` tinyint(1) NOT NULL,
  `shouldAllowUnlink` tinyint(1) NOT NULL,
  `properties` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_provider` (`providerType`,`providerDomain`),
  KEY `key_class` (`providerClass`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `auth_providerconfigtransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_doorkeeper` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_doorkeeper`;

CREATE TABLE `doorkeeper_externalobject` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectKey` char(12) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `applicationType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `applicationDomain` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectURI` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `importerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_object` (`objectKey`),
  KEY `key_full` (`applicationType`,`applicationDomain`,`objectType`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_legalpad` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_legalpad`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `legalpad_document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `title` varchar(255) NOT NULL,
  `contributorCount` int(10) unsigned NOT NULL DEFAULT '0',
  `recentContributorPHIDs` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `creatorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `versions` int(10) unsigned NOT NULL DEFAULT '0',
  `documentBodyPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_creator` (`creatorPHID`,`dateModified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `legalpad_documentbody` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `creatorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `documentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  `text` longtext,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_document` (`documentPHID`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `legalpad_documentsignature` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `documentVersion` int(10) unsigned NOT NULL DEFAULT '0',
  `signerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `signatureData` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `secretKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `verified` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `key_signer` (`signerPHID`,`dateModified`),
  KEY `secretKey` (`secretKey`),
  KEY `key_document` (`documentPHID`,`signerPHID`,`documentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `legalpad_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `legalpad_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `documentID` int(10) unsigned DEFAULT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `fixedState` varchar(12) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `hasReplies` tinyint(1) NOT NULL,
  `replyToCommentPHID` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`documentID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_policy` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_policy`;

CREATE TABLE `policy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `rules` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `defaultAction` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_nuance` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_nuance`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ownerPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `requestorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sourcePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sourceLabel` varchar(255) DEFAULT NULL,
  `status` int(10) unsigned NOT NULL,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `dateNuanced` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_source` (`sourcePHID`,`status`,`dateNuanced`,`id`),
  KEY `key_owner` (`ownerPHID`,`status`,`dateNuanced`,`id`),
  KEY `key_contacter` (`requestorPHID`,`status`,`dateNuanced`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_itemtransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_itemtransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) NOT NULL,
  `editPolicy` varchar(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_queueitem` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `queuePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `itemPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `itemStatus` int(10) unsigned NOT NULL,
  `itemDateNuanced` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_one_per_queue` (`itemPHID`,`queuePHID`),
  KEY `key_queue` (`queuePHID`,`itemStatus`,`itemDateNuanced`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_queuetransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_queuetransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_requestor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_requestorsource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `requestorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sourcePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sourceKey` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_source_key` (`sourcePHID`,`sourceKey`),
  KEY `key_requestor` (`requestorPHID`,`id`),
  KEY `key_source` (`sourcePHID`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_requestortransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_requestortransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mailKey` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) NOT NULL,
  `editPolicy` varchar(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_type` (`type`,`dateModified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_sourcetransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `nuance_sourcetransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `transactionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_passphrase` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_passphrase`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `passphrase_credential` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `credentialType` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `providesType` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `description` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `username` varchar(255) NOT NULL,
  `secretID` int(10) unsigned DEFAULT NULL,
  `isDestroyed` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_secret` (`secretID`),
  KEY `key_type` (`credentialType`),
  KEY `key_provides` (`providesType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `passphrase_credentialtransaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `commentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `transactionType` varchar(32) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `oldValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `newValue` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `contentSource` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `metadata` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `passphrase_secret` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `secretData` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE DATABASE `{$NAMESPACE}_phragment` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `{$NAMESPACE}_phragment`;

CREATE TABLE `edge` (
  `src` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dst` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `seq` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`src`,`type`,`dst`),
  KEY `src` (`src`,`type`,`dateCreated`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phragment_fragment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `path` varchar(254) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `depth` int(10) unsigned NOT NULL,
  `latestVersionPHID` varchar(64) DEFAULT NULL,
  `viewPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `editPolicy` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phragment_fragmentversion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `fragmentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `filePHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_version` (`fragmentPHID`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phragment_snapshot` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `primaryFragmentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(192) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `description` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`primaryFragmentPHID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `phragment_snapshotchild` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `snapshotPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fragmentPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `fragmentVersionPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_child` (`snapshotPHID`,`fragmentPHID`,`fragmentVersionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
