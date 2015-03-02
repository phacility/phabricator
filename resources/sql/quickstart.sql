CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_audit` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_audit`;

CREATE TABLE `audit_transaction` (
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

CREATE TABLE `audit_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `commitPHID` varbinary(64) DEFAULT NULL,
  `pathID` int(10) unsigned DEFAULT NULL,
  `isNewFile` tinyint(1) NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `fixedState` varchar(12) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `hasReplies` tinyint(1) NOT NULL,
  `replyToCommentPHID` varbinary(64) DEFAULT NULL,
  `legacyCommentID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  KEY `key_path` (`pathID`),
  KEY `key_draft` (`authorPHID`,`transactionPHID`),
  KEY `key_commit` (`commitPHID`),
  KEY `key_legacy` (`legacyCommentID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_calendar` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_calendar`;

CREATE TABLE `calendar_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `userPHID` varbinary(64) NOT NULL,
  `dateFrom` int(10) unsigned NOT NULL,
  `dateTo` int(10) unsigned NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `userPHID_dateFrom` (`userPHID`,`dateTo`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_holiday` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `day` date NOT NULL,
  `name` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_chatlog` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_chatlog`;

CREATE TABLE `chatlog_channel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `serviceName` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `serviceType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `channelName` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_channel` (`channelName`,`serviceType`,`serviceName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `chatlog_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `epoch` int(10) unsigned NOT NULL,
  `author` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `type` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `message` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `loggedByPHID` varbinary(64) NOT NULL,
  `channelID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `channel` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_conduit` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_conduit`;

CREATE TABLE `conduit_certificatetoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `token` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conduit_connectionlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `clientVersion` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `clientDescription` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `username` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_created` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conduit_methodcalllog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connectionID` bigint(20) unsigned DEFAULT NULL,
  `method` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `error` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `duration` bigint(20) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `callerPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `key_method` (`method`),
  KEY `key_callermethod` (`callerPHID`,`method`),
  KEY `key_date` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conduit_token` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `tokenType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `token` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `expires` int(10) unsigned DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_token` (`token`),
  KEY `key_object` (`objectPHID`,`tokenType`),
  KEY `key_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_countdown` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_countdown`;

CREATE TABLE `countdown` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_daemon` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_daemon`;

CREATE TABLE `daemon_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `daemon` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `host` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `argv` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `explicitArgv` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `envHash` binary(40) NOT NULL,
  `status` varchar(8) COLLATE {$COLLATE_TEXT} NOT NULL,
  `runningAsUser` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `envInfo` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `daemonID` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_daemonID` (`daemonID`),
  KEY `status` (`status`),
  KEY `dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `daemon_logevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `logID` int(10) unsigned NOT NULL,
  `logType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `message` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `logID` (`logID`,`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_differential` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_differential`;

CREATE TABLE `differential_affectedpath` (
  `repositoryID` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `revisionID` int(10) unsigned NOT NULL,
  KEY `repositoryID` (`repositoryID`,`pathID`,`epoch`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_changeset` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `diffID` int(10) unsigned NOT NULL,
  `oldFile` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `filename` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `awayPaths` longtext COLLATE {$COLLATE_TEXT},
  `changeType` int(10) unsigned NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT},
  `oldProperties` longtext COLLATE {$COLLATE_TEXT},
  `newProperties` longtext COLLATE {$COLLATE_TEXT},
  `addLines` int(10) unsigned NOT NULL,
  `delLines` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `diffID` (`diffID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_changeset_parse_cache` (
  `id` int(10) unsigned NOT NULL,
  `cache` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_commit` (
  `revisionID` int(10) unsigned NOT NULL,
  `commitPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`revisionID`,`commitPHID`),
  UNIQUE KEY `commitPHID` (`commitPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_diff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `revisionID` int(10) unsigned DEFAULT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `repositoryPHID` varbinary(64) DEFAULT NULL,
  `sourceMachine` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `sourcePath` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `sourceControlSystem` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `sourceControlBaseRevision` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `sourceControlPath` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `lintStatus` int(10) unsigned NOT NULL,
  `unitStatus` int(10) unsigned NOT NULL,
  `lineCount` int(10) unsigned NOT NULL,
  `branch` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `bookmark` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `arcanistProjectPHID` varbinary(64) DEFAULT NULL,
  `creationMethod` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `repositoryUUID` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_diffproperty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `diffID` int(10) unsigned NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diffID` (`diffID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_difftransaction` (
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

CREATE TABLE `differential_draft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `draftKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_unique` (`objectPHID`,`authorPHID`,`draftKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_hunk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `changesetID` int(10) unsigned NOT NULL,
  `changes` longtext COLLATE {$COLLATE_TEXT},
  `oldOffset` int(10) unsigned NOT NULL,
  `oldLen` int(10) unsigned NOT NULL,
  `newOffset` int(10) unsigned NOT NULL,
  `newLen` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `changesetID` (`changesetID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_hunk_modern` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `changesetID` int(10) unsigned NOT NULL,
  `oldOffset` int(10) unsigned NOT NULL,
  `oldLen` int(10) unsigned NOT NULL,
  `newOffset` int(10) unsigned NOT NULL,
  `newLen` int(10) unsigned NOT NULL,
  `dataType` binary(4) NOT NULL,
  `dataEncoding` varchar(16) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dataFormat` binary(4) NOT NULL,
  `data` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_changeset` (`changesetID`),
  KEY `key_created` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_revision` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `originalTitle` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `summary` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `testPlan` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `lastReviewerPHID` varbinary(64) DEFAULT NULL,
  `lineCount` int(10) unsigned DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `attached` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(40) NOT NULL,
  `branchName` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `arcanistProjectPHID` varbinary(64) DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`,`status`),
  KEY `repositoryPHID` (`repositoryPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_revisionhash` (
  `revisionID` int(10) unsigned NOT NULL,
  `type` binary(4) NOT NULL,
  `hash` binary(40) NOT NULL,
  KEY `type` (`type`,`hash`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `differential_transaction` (
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

CREATE TABLE `differential_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `revisionPHID` varbinary(64) DEFAULT NULL,
  `changesetID` int(10) unsigned DEFAULT NULL,
  `isNewFile` tinyint(1) NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `fixedState` varchar(12) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `hasReplies` tinyint(1) NOT NULL,
  `replyToCommentPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  KEY `key_changeset` (`changesetID`),
  KEY `key_draft` (`authorPHID`,`transactionPHID`),
  KEY `key_revision` (`revisionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_draft` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_draft`;

CREATE TABLE `draft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) NOT NULL,
  `draftKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `draft` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorPHID` (`authorPHID`,`draftKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_drydock` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_drydock`;

CREATE TABLE `drydock_blueprint` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `className` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `blueprintName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_blueprinttransaction` (
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

CREATE TABLE `drydock_lease` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `resourceID` int(10) unsigned DEFAULT NULL,
  `status` int(10) unsigned NOT NULL,
  `until` int(10) unsigned DEFAULT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `attributes` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `taskID` int(10) unsigned DEFAULT NULL,
  `resourceType` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resourceID` int(10) unsigned DEFAULT NULL,
  `leaseID` int(10) unsigned DEFAULT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `message` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resourceID` (`resourceID`,`epoch`),
  KEY `leaseID` (`leaseID`,`epoch`),
  KEY `epoch` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_resource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `status` int(10) unsigned NOT NULL,
  `type` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `attributes` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `capabilities` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `blueprintPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_feed` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_feed`;

CREATE TABLE `feed_storydata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `chronologicalKey` bigint(20) unsigned NOT NULL,
  `storyType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `storyData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chronologicalKey` (`chronologicalKey`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `feed_storynotification` (
  `userPHID` varbinary(64) NOT NULL,
  `primaryObjectPHID` varbinary(64) NOT NULL,
  `chronologicalKey` bigint(20) unsigned NOT NULL,
  `hasViewed` tinyint(1) NOT NULL,
  UNIQUE KEY `userPHID` (`userPHID`,`chronologicalKey`),
  KEY `userPHID_2` (`userPHID`,`hasViewed`,`primaryObjectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `feed_storyreference` (
  `objectPHID` varbinary(64) NOT NULL,
  `chronologicalKey` bigint(20) unsigned NOT NULL,
  UNIQUE KEY `objectPHID` (`objectPHID`,`chronologicalKey`),
  KEY `chronologicalKey` (`chronologicalKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_file` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_file`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `mimeType` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `byteSize` bigint(20) unsigned NOT NULL,
  `storageEngine` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `storageFormat` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `storageHandle` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `secretKey` binary(20) DEFAULT NULL,
  `contentHash` binary(40) DEFAULT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `ttl` int(10) unsigned DEFAULT NULL,
  `isExplicitUpload` tinyint(1) DEFAULT '1',
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`),
  KEY `contentHash` (`contentHash`),
  KEY `key_ttl` (`ttl`),
  KEY `key_dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_imagemacro` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `filePHID` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  `audioPHID` varbinary(64) DEFAULT NULL,
  `audioBehavior` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `name` (`name`),
  KEY `key_disabled` (`isDisabled`),
  KEY `key_dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_storageblob` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_transaction` (
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

CREATE TABLE `file_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_transformedfile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `originalPHID` varbinary(64) NOT NULL,
  `transform` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `transformedPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `originalPHID` (`originalPHID`,`transform`),
  KEY `transformedPHID` (`transformedPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `macro_transaction` (
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
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `macro_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_flag` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_flag`;

CREATE TABLE `flag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ownerPHID` varbinary(64) NOT NULL,
  `type` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `reasonPHID` varbinary(64) NOT NULL,
  `color` int(10) unsigned NOT NULL,
  `note` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ownerPHID` (`ownerPHID`,`type`,`objectPHID`),
  KEY `objectPHID` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_harbormaster` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_harbormaster`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_build` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `buildablePHID` varbinary(64) NOT NULL,
  `buildPlanPHID` varbinary(64) NOT NULL,
  `buildStatus` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `buildGeneration` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_buildable` (`buildablePHID`),
  KEY `key_plan` (`buildPlanPHID`),
  KEY `key_status` (`buildStatus`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `buildablePHID` varbinary(64) NOT NULL,
  `containerPHID` varbinary(64) DEFAULT NULL,
  `buildableStatus` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isManualBuildable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_buildable` (`buildablePHID`),
  KEY `key_container` (`containerPHID`),
  KEY `key_manual` (`isManualBuildable`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildabletransaction` (
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

CREATE TABLE `harbormaster_buildartifact` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artifactType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `artifactIndex` binary(12) NOT NULL,
  `artifactKey` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `artifactData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `buildTargetPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_artifact` (`artifactType`,`artifactIndex`),
  KEY `key_garbagecollect` (`artifactType`,`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildcommand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) NOT NULL,
  `targetPHID` varbinary(64) NOT NULL,
  `command` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_target` (`targetPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `logSource` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `logType` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `duration` int(10) unsigned DEFAULT NULL,
  `live` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `buildTargetPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_buildtarget` (`buildTargetPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildlogchunk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `logID` int(10) unsigned NOT NULL,
  `encoding` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `size` int(10) unsigned DEFAULT NULL,
  `chunk` longblob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_log` (`logID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) NOT NULL,
  `buildTargetPHID` varbinary(64) NOT NULL,
  `type` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isConsumed` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_buildtarget` (`buildTargetPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildplan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `planStatus` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_status` (`planStatus`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildplantransaction` (
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

CREATE TABLE `harbormaster_buildstep` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `buildPlanPHID` varbinary(64) NOT NULL,
  `className` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_plan` (`buildPlanPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildsteptransaction` (
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

CREATE TABLE `harbormaster_buildtarget` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `buildPHID` varbinary(64) NOT NULL,
  `buildStepPHID` varbinary(64) NOT NULL,
  `className` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `variables` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `targetStatus` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateStarted` int(10) unsigned DEFAULT NULL,
  `dateCompleted` int(10) unsigned DEFAULT NULL,
  `buildGeneration` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_build` (`buildPHID`,`buildStepPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildtransaction` (
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

CREATE TABLE `harbormaster_object` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_scratchtable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `bigData` longtext COLLATE {$COLLATE_TEXT},
  `nonmutableData` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `lisk_counter` (
  `counterName` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `counterValue` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`counterName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_herald` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_herald`;

CREATE TABLE `herald_action` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `action` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `target` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruleID` (`ruleID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_condition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `fieldName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `fieldCondition` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `value` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruleID` (`ruleID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `contentType` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `mustMatchAll` tinyint(1) NOT NULL,
  `configVersion` int(10) unsigned NOT NULL DEFAULT '1',
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `repetitionPolicy` int(10) unsigned DEFAULT NULL,
  `ruleType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `isDisabled` int(10) unsigned NOT NULL DEFAULT '0',
  `triggerObjectPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_trigger` (`triggerObjectPHID`),
  KEY `key_author` (`authorPHID`),
  KEY `key_ruletype` (`ruleType`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_ruleapplied` (
  `ruleID` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  PRIMARY KEY (`ruleID`,`phid`),
  KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_ruleedit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `editorPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `ruleName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `action` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ruleID` (`ruleID`,`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_ruletransaction` (
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

CREATE TABLE `herald_ruletransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_savedheader` (
  `phid` varbinary(64) NOT NULL,
  `header` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_transcript` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `host` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `duration` double NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `dryRun` tinyint(1) NOT NULL,
  `objectTranscript` longblob NOT NULL,
  `ruleTranscripts` longblob NOT NULL,
  `conditionTranscripts` longblob NOT NULL,
  `applyTranscripts` longblob NOT NULL,
  `garbageCollected` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `objectPHID` (`objectPHID`),
  KEY `garbageCollected` (`garbageCollected`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_maniphest` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_maniphest`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `maniphest_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `maniphest_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `maniphest_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `maniphest_nameindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `indexedObjectPHID` varbinary(64) NOT NULL,
  `indexedObjectName` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`indexedObjectPHID`),
  KEY `key_name` (`indexedObjectName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `maniphest_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `attached` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  `priority` int(10) unsigned NOT NULL,
  `title` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `originalTitle` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `projectPHIDs` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `ownerOrdering` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `originalEmailSource` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `subpriority` double NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `priority` (`priority`,`status`),
  KEY `status` (`status`),
  KEY `ownerPHID` (`ownerPHID`,`status`),
  KEY `authorPHID` (`authorPHID`,`status`),
  KEY `ownerOrdering` (`ownerOrdering`),
  KEY `priority_2` (`priority`,`subpriority`),
  KEY `key_dateCreated` (`dateCreated`),
  KEY `key_dateModified` (`dateModified`),
  KEY `key_title` (`title`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `maniphest_transaction` (
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

CREATE TABLE `maniphest_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_meta_data` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_meta_data`;

CREATE TABLE `patch_status` (
  `patch` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `applied` int(10) unsigned NOT NULL,
  PRIMARY KEY (`patch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

INSERT INTO `patch_status` VALUES ('phabricator:000.project.sql',1425312505),('phabricator:0000.legacy.sql',1425312505),('phabricator:001.maniphest_projects.sql',1425312505),('phabricator:002.oauth.sql',1425312505),('phabricator:003.more_oauth.sql',1425312505),('phabricator:004.daemonrepos.sql',1425312505),('phabricator:005.workers.sql',1425312505),('phabricator:006.repository.sql',1425312505),('phabricator:007.daemonlog.sql',1425312505),('phabricator:008.repoopt.sql',1425312505),('phabricator:009.repo_summary.sql',1425312505),('phabricator:010.herald.sql',1425312505),('phabricator:011.badcommit.sql',1425312505),('phabricator:012.dropphidtype.sql',1425312505),('phabricator:013.commitdetail.sql',1425312505),('phabricator:014.shortcuts.sql',1425312505),('phabricator:015.preferences.sql',1425312505),('phabricator:016.userrealnameindex.sql',1425312505),('phabricator:017.sessionkeys.sql',1425312505),('phabricator:018.owners.sql',1425312505),('phabricator:019.arcprojects.sql',1425312505),('phabricator:020.pathcapital.sql',1425312505),('phabricator:021.xhpastview.sql',1425312505),('phabricator:022.differentialcommit.sql',1425312505),('phabricator:023.dxkeys.sql',1425312505),('phabricator:024.mlistkeys.sql',1425312505),('phabricator:025.commentopt.sql',1425312505),('phabricator:026.diffpropkey.sql',1425312505),('phabricator:027.metamtakeys.sql',1425312505),('phabricator:028.systemagent.sql',1425312505),('phabricator:029.cursors.sql',1425312505),('phabricator:030.imagemacro.sql',1425312505),('phabricator:031.workerrace.sql',1425312505),('phabricator:032.viewtime.sql',1425312505),('phabricator:033.privtest.sql',1425312505),('phabricator:034.savedheader.sql',1425312505),('phabricator:035.proxyimage.sql',1425312505),('phabricator:036.mailkey.sql',1425312505),('phabricator:037.setuptest.sql',1425312505),('phabricator:038.admin.sql',1425312505),('phabricator:039.userlog.sql',1425312505),('phabricator:040.transform.sql',1425312505),('phabricator:041.heraldrepetition.sql',1425312506),('phabricator:042.commentmetadata.sql',1425312506),('phabricator:043.pastebin.sql',1425312506),('phabricator:044.countdown.sql',1425312506),('phabricator:045.timezone.sql',1425312506),('phabricator:046.conduittoken.sql',1425312506),('phabricator:047.projectstatus.sql',1425312506),('phabricator:048.relationshipkeys.sql',1425312506),('phabricator:049.projectowner.sql',1425312506),('phabricator:050.taskdenormal.sql',1425312506),('phabricator:051.projectfilter.sql',1425312506),('phabricator:052.pastelanguage.sql',1425312506),('phabricator:053.feed.sql',1425312506),('phabricator:054.subscribers.sql',1425312506),('phabricator:055.add_author_to_files.sql',1425312506),('phabricator:056.slowvote.sql',1425312506),('phabricator:057.parsecache.sql',1425312506),('phabricator:058.missingkeys.sql',1425312506),('phabricator:059.engines.php',1425312506),('phabricator:060.phriction.sql',1425312506),('phabricator:061.phrictioncontent.sql',1425312506),('phabricator:062.phrictionmenu.sql',1425312506),('phabricator:063.pasteforks.sql',1425312506),('phabricator:064.subprojects.sql',1425312506),('phabricator:065.sshkeys.sql',1425312506),('phabricator:066.phrictioncontent.sql',1425312506),('phabricator:067.preferences.sql',1425312506),('phabricator:068.maniphestauxiliarystorage.sql',1425312506),('phabricator:069.heraldxscript.sql',1425312506),('phabricator:070.differentialaux.sql',1425312506),('phabricator:071.contentsource.sql',1425312506),('phabricator:072.blamerevert.sql',1425312506),('phabricator:073.reposymbols.sql',1425312506),('phabricator:074.affectedpath.sql',1425312506),('phabricator:075.revisionhash.sql',1425312506),('phabricator:076.indexedlanguages.sql',1425312506),('phabricator:077.originalemail.sql',1425312506),('phabricator:078.nametoken.sql',1425312506),('phabricator:079.nametokenindex.php',1425312506),('phabricator:080.filekeys.sql',1425312506),('phabricator:081.filekeys.php',1425312506),('phabricator:082.xactionkey.sql',1425312506),('phabricator:083.dxviewtime.sql',1425312506),('phabricator:084.pasteauthorkey.sql',1425312506),('phabricator:085.packagecommitrelationship.sql',1425312506),('phabricator:086.formeraffil.sql',1425312506),('phabricator:087.phrictiondelete.sql',1425312506),('phabricator:088.audit.sql',1425312506),('phabricator:089.projectwiki.sql',1425312506),('phabricator:090.forceuniqueprojectnames.php',1425312506),('phabricator:091.uniqueslugkey.sql',1425312506),('phabricator:092.dropgithubnotification.sql',1425312506),('phabricator:093.gitremotes.php',1425312506),('phabricator:094.phrictioncolumn.sql',1425312506),('phabricator:095.directory.sql',1425312506),('phabricator:096.filename.sql',1425312506),('phabricator:097.heraldruletypes.sql',1425312506),('phabricator:098.heraldruletypemigration.php',1425312506),('phabricator:099.drydock.sql',1425312506),('phabricator:100.projectxaction.sql',1425312506),('phabricator:101.heraldruleapplied.sql',1425312506),('phabricator:102.heraldcleanup.php',1425312506),('phabricator:103.heraldedithistory.sql',1425312507),('phabricator:104.searchkey.sql',1425312507),('phabricator:105.mimetype.sql',1425312507),('phabricator:106.chatlog.sql',1425312507),('phabricator:107.oauthserver.sql',1425312507),('phabricator:108.oauthscope.sql',1425312507),('phabricator:109.oauthclientphidkey.sql',1425312507),('phabricator:110.commitaudit.sql',1425312507),('phabricator:111.commitauditmigration.php',1425312507),('phabricator:112.oauthaccesscoderedirecturi.sql',1425312507),('phabricator:113.lastreviewer.sql',1425312507),('phabricator:114.auditrequest.sql',1425312507),('phabricator:115.prepareutf8.sql',1425312507),('phabricator:116.utf8-backup-first-expect-wait.sql',1425312508),('phabricator:117.repositorydescription.php',1425312508),('phabricator:118.auditinline.sql',1425312508),('phabricator:119.filehash.sql',1425312508),('phabricator:120.noop.sql',1425312508),('phabricator:121.drydocklog.sql',1425312508),('phabricator:122.flag.sql',1425312508),('phabricator:123.heraldrulelog.sql',1425312508),('phabricator:124.subpriority.sql',1425312508),('phabricator:125.ipv6.sql',1425312508),('phabricator:126.edges.sql',1425312509),('phabricator:127.userkeybody.sql',1425312509),('phabricator:128.phabricatorcom.sql',1425312509),('phabricator:129.savedquery.sql',1425312509),('phabricator:130.denormalrevisionquery.sql',1425312509),('phabricator:131.migraterevisionquery.php',1425312509),('phabricator:132.phame.sql',1425312509),('phabricator:133.imagemacro.sql',1425312509),('phabricator:134.emptysearch.sql',1425312509),('phabricator:135.datecommitted.sql',1425312509),('phabricator:136.sex.sql',1425312509),('phabricator:137.auditmetadata.sql',1425312509),('phabricator:138.notification.sql',1425312509),('phabricator:20121209.pholioxactions.sql',1425312509),('phabricator:20121209.xmacroadd.sql',1425312510),('phabricator:20121209.xmacromigrate.php',1425312510),('phabricator:20121209.xmacromigratekey.sql',1425312510),('phabricator:20121220.generalcache.sql',1425312510),('phabricator:20121226.config.sql',1425312510),('phabricator:20130101.confxaction.sql',1425312510),('phabricator:20130102.metamtareceivedmailmessageidhash.sql',1425312510),('phabricator:20130103.filemetadata.sql',1425312510),('phabricator:20130111.conpherence.sql',1425312510),('phabricator:20130127.altheraldtranscript.sql',1425312510),('phabricator:20130131.conpherencepics.sql',1425312510),('phabricator:20130201.revisionunsubscribed.php',1425312510),('phabricator:20130201.revisionunsubscribed.sql',1425312510),('phabricator:20130214.chatlogchannel.sql',1425312510),('phabricator:20130214.chatlogchannelid.sql',1425312510),('phabricator:20130214.token.sql',1425312510),('phabricator:20130215.phabricatorfileaddttl.sql',1425312510),('phabricator:20130217.cachettl.sql',1425312510),('phabricator:20130218.longdaemon.sql',1425312510),('phabricator:20130218.updatechannelid.php',1425312510),('phabricator:20130219.commitsummary.sql',1425312510),('phabricator:20130219.commitsummarymig.php',1425312510),('phabricator:20130222.dropchannel.sql',1425312510),('phabricator:20130226.commitkey.sql',1425312510),('phabricator:20130304.lintauthor.sql',1425312510),('phabricator:20130310.xactionmeta.sql',1425312510),('phabricator:20130317.phrictionedge.sql',1425312510),('phabricator:20130319.conpherence.sql',1425312510),('phabricator:20130319.phabricatorfileexplicitupload.sql',1425312510),('phabricator:20130320.phlux.sql',1425312510),('phabricator:20130321.token.sql',1425312510),('phabricator:20130322.phortune.sql',1425312510),('phabricator:20130323.phortunepayment.sql',1425312510),('phabricator:20130324.phortuneproduct.sql',1425312510),('phabricator:20130330.phrequent.sql',1425312510),('phabricator:20130403.conpherencecache.sql',1425312510),('phabricator:20130403.conpherencecachemig.php',1425312510),('phabricator:20130409.commitdrev.php',1425312510),('phabricator:20130417.externalaccount.sql',1425312510),('phabricator:20130423.conpherenceindices.sql',1425312510),('phabricator:20130423.phortunepaymentrevised.sql',1425312510),('phabricator:20130423.updateexternalaccount.sql',1425312510),('phabricator:20130426.search_savedquery.sql',1425312510),('phabricator:20130502.countdownrevamp1.sql',1425312510),('phabricator:20130502.countdownrevamp2.php',1425312510),('phabricator:20130502.countdownrevamp3.sql',1425312510),('phabricator:20130507.releephrqmailkey.sql',1425312510),('phabricator:20130507.releephrqmailkeypop.php',1425312510),('phabricator:20130507.releephrqsimplifycols.sql',1425312510),('phabricator:20130508.releephtransactions.sql',1425312510),('phabricator:20130508.releephtransactionsmig.php',1425312510),('phabricator:20130508.search_namedquery.sql',1425312510),('phabricator:20130513.receviedmailstatus.sql',1425312510),('phabricator:20130519.diviner.sql',1425312510),('phabricator:20130521.dropconphimages.sql',1425312510),('phabricator:20130523.maniphest_owners.sql',1425312510),('phabricator:20130524.repoxactions.sql',1425312510),('phabricator:20130529.macroauthor.sql',1425312510),('phabricator:20130529.macroauthormig.php',1425312510),('phabricator:20130530.macrodatekey.sql',1425312510),('phabricator:20130530.pastekeys.sql',1425312510),('phabricator:20130530.sessionhash.php',1425312510),('phabricator:20130531.filekeys.sql',1425312511),('phabricator:20130602.morediviner.sql',1425312511),('phabricator:20130602.namedqueries.sql',1425312511),('phabricator:20130606.userxactions.sql',1425312511),('phabricator:20130607.xaccount.sql',1425312511),('phabricator:20130611.migrateoauth.php',1425312511),('phabricator:20130611.nukeldap.php',1425312511),('phabricator:20130613.authdb.sql',1425312511),('phabricator:20130619.authconf.php',1425312511),('phabricator:20130620.diffxactions.sql',1425312511),('phabricator:20130621.diffcommentphid.sql',1425312511),('phabricator:20130621.diffcommentphidmig.php',1425312511),('phabricator:20130621.diffcommentunphid.sql',1425312511),('phabricator:20130622.doorkeeper.sql',1425312511),('phabricator:20130628.legalpadv0.sql',1425312511),('phabricator:20130701.conduitlog.sql',1425312511),('phabricator:20130703.legalpaddocdenorm.php',1425312511),('phabricator:20130703.legalpaddocdenorm.sql',1425312511),('phabricator:20130709.droptimeline.sql',1425312511),('phabricator:20130709.legalpadsignature.sql',1425312511),('phabricator:20130711.pholioimageobsolete.php',1425312511),('phabricator:20130711.pholioimageobsolete.sql',1425312511),('phabricator:20130711.pholioimageobsolete2.sql',1425312511),('phabricator:20130711.trimrealnames.php',1425312511),('phabricator:20130714.votexactions.sql',1425312511),('phabricator:20130715.votecomments.php',1425312511),('phabricator:20130715.voteedges.sql',1425312511),('phabricator:20130716.archivememberlessprojects.php',1425312511),('phabricator:20130722.pholioreplace.sql',1425312511),('phabricator:20130723.taskstarttime.sql',1425312511),('phabricator:20130726.ponderxactions.sql',1425312511),('phabricator:20130727.ponderquestionstatus.sql',1425312511),('phabricator:20130728.ponderunique.php',1425312511),('phabricator:20130728.ponderuniquekey.sql',1425312511),('phabricator:20130728.ponderxcomment.php',1425312511),('phabricator:20130731.releephcutpointidentifier.sql',1425312511),('phabricator:20130731.releephproject.sql',1425312511),('phabricator:20130731.releephrepoid.sql',1425312511),('phabricator:20130801.pastexactions.php',1425312511),('phabricator:20130801.pastexactions.sql',1425312511),('phabricator:20130802.heraldphid.sql',1425312511),('phabricator:20130802.heraldphids.php',1425312511),('phabricator:20130802.heraldphidukey.sql',1425312511),('phabricator:20130802.heraldxactions.sql',1425312511),('phabricator:20130805.pasteedges.sql',1425312511),('phabricator:20130805.pastemailkey.sql',1425312511),('phabricator:20130805.pastemailkeypop.php',1425312511),('phabricator:20130814.usercustom.sql',1425312512),('phabricator:20130820.file-mailkey-populate.php',1425312512),('phabricator:20130820.filemailkey.sql',1425312512),('phabricator:20130820.filexactions.sql',1425312512),('phabricator:20130820.releephxactions.sql',1425312512),('phabricator:20130826.divinernode.sql',1425312512),('phabricator:20130912.maniphest.1.touch.sql',1425312512),('phabricator:20130912.maniphest.2.created.sql',1425312512),('phabricator:20130912.maniphest.3.nameindex.sql',1425312512),('phabricator:20130912.maniphest.4.fillindex.php',1425312512),('phabricator:20130913.maniphest.1.migratesearch.php',1425312512),('phabricator:20130914.usercustom.sql',1425312512),('phabricator:20130915.maniphestcustom.sql',1425312512),('phabricator:20130915.maniphestmigrate.php',1425312512),('phabricator:20130915.maniphestqdrop.sql',1425312512),('phabricator:20130919.mfieldconf.php',1425312512),('phabricator:20130920.repokeyspolicy.sql',1425312512),('phabricator:20130921.mtransactions.sql',1425312512),('phabricator:20130921.xmigratemaniphest.php',1425312512),('phabricator:20130923.mrename.sql',1425312512),('phabricator:20130924.mdraftkey.sql',1425312512),('phabricator:20130925.mpolicy.sql',1425312512),('phabricator:20130925.xpolicy.sql',1425312512),('phabricator:20130926.dcustom.sql',1425312512),('phabricator:20130926.dinkeys.sql',1425312512),('phabricator:20130926.dinline.php',1425312512),('phabricator:20130927.audiomacro.sql',1425312512),('phabricator:20130929.filepolicy.sql',1425312512),('phabricator:20131004.dxedgekey.sql',1425312512),('phabricator:20131004.dxreviewers.php',1425312512),('phabricator:20131006.hdisable.sql',1425312512),('phabricator:20131010.pstorage.sql',1425312512),('phabricator:20131015.cpolicy.sql',1425312512),('phabricator:20131020.col1.sql',1425312512),('phabricator:20131020.harbormaster.sql',1425312512),('phabricator:20131020.pcustom.sql',1425312512),('phabricator:20131020.pxaction.sql',1425312512),('phabricator:20131020.pxactionmig.php',1425312512),('phabricator:20131025.repopush.sql',1425312512),('phabricator:20131026.commitstatus.sql',1425312512),('phabricator:20131030.repostatusmessage.sql',1425312512),('phabricator:20131031.vcspassword.sql',1425312512),('phabricator:20131105.buildstep.sql',1425312512),('phabricator:20131106.diffphid.1.col.sql',1425312512),('phabricator:20131106.diffphid.2.mig.php',1425312512),('phabricator:20131106.diffphid.3.key.sql',1425312512),('phabricator:20131106.nuance-v0.sql',1425312512),('phabricator:20131107.buildlog.sql',1425312512),('phabricator:20131112.userverified.1.col.sql',1425312512),('phabricator:20131112.userverified.2.mig.php',1425312512),('phabricator:20131118.ownerorder.php',1425312512),('phabricator:20131119.passphrase.sql',1425312512),('phabricator:20131120.nuancesourcetype.sql',1425312513),('phabricator:20131121.passphraseedge.sql',1425312513),('phabricator:20131121.repocredentials.1.col.sql',1425312513),('phabricator:20131121.repocredentials.2.mig.php',1425312513),('phabricator:20131122.repomirror.sql',1425312513),('phabricator:20131123.drydockblueprintpolicy.sql',1425312513),('phabricator:20131129.drydockresourceblueprint.sql',1425312513),('phabricator:20131204.pushlog.sql',1425312513),('phabricator:20131205.buildsteporder.sql',1425312513),('phabricator:20131205.buildstepordermig.php',1425312513),('phabricator:20131205.buildtargets.sql',1425312513),('phabricator:20131206.phragment.sql',1425312513),('phabricator:20131206.phragmentnull.sql',1425312513),('phabricator:20131208.phragmentsnapshot.sql',1425312513),('phabricator:20131211.phragmentedges.sql',1425312513),('phabricator:20131217.pushlogphid.1.col.sql',1425312513),('phabricator:20131217.pushlogphid.2.mig.php',1425312513),('phabricator:20131217.pushlogphid.3.key.sql',1425312513),('phabricator:20131219.pxdrop.sql',1425312513),('phabricator:20131224.harbormanual.sql',1425312513),('phabricator:20131227.heraldobject.sql',1425312513),('phabricator:20131231.dropshortcut.sql',1425312513),('phabricator:20131302.maniphestvalue.sql',1425312510),('phabricator:20140104.harbormastercmd.sql',1425312513),('phabricator:20140106.macromailkey.1.sql',1425312513),('phabricator:20140106.macromailkey.2.php',1425312513),('phabricator:20140108.ddbpname.1.sql',1425312513),('phabricator:20140108.ddbpname.2.php',1425312513),('phabricator:20140109.ddxactions.sql',1425312513),('phabricator:20140109.projectcolumnsdates.sql',1425312513),('phabricator:20140113.legalpadsig.1.sql',1425312513),('phabricator:20140113.legalpadsig.2.php',1425312513),('phabricator:20140115.auth.1.id.sql',1425312513),('phabricator:20140115.auth.2.expires.sql',1425312513),('phabricator:20140115.auth.3.unlimit.php',1425312513),('phabricator:20140115.legalpadsigkey.sql',1425312513),('phabricator:20140116.reporefcursor.sql',1425312513),('phabricator:20140126.diff.1.parentrevisionid.sql',1425312513),('phabricator:20140126.diff.2.repositoryphid.sql',1425312513),('phabricator:20140130.dash.1.board.sql',1425312513),('phabricator:20140130.dash.2.panel.sql',1425312513),('phabricator:20140130.dash.3.boardxaction.sql',1425312513),('phabricator:20140130.dash.4.panelxaction.sql',1425312513),('phabricator:20140130.mail.1.retry.sql',1425312513),('phabricator:20140130.mail.2.next.sql',1425312513),('phabricator:20140201.gc.1.mailsent.sql',1425312513),('phabricator:20140201.gc.2.mailreceived.sql',1425312513),('phabricator:20140205.cal.1.rename.sql',1425312513),('phabricator:20140205.cal.2.phid-col.sql',1425312513),('phabricator:20140205.cal.3.phid-mig.php',1425312513),('phabricator:20140205.cal.4.phid-key.sql',1425312513),('phabricator:20140210.herald.rule-condition-mig.php',1425312513),('phabricator:20140210.projcfield.1.blurb.php',1425312513),('phabricator:20140210.projcfield.2.piccol.sql',1425312513),('phabricator:20140210.projcfield.3.picmig.sql',1425312513),('phabricator:20140210.projcfield.4.memmig.sql',1425312513),('phabricator:20140210.projcfield.5.dropprofile.sql',1425312513),('phabricator:20140211.dx.1.nullablechangesetid.sql',1425312513),('phabricator:20140211.dx.2.migcommenttext.php',1425312513),('phabricator:20140211.dx.3.migsubscriptions.sql',1425312513),('phabricator:20140211.dx.999.drop.relationships.sql',1425312513),('phabricator:20140212.dx.1.armageddon.php',1425312513),('phabricator:20140214.clean.1.legacycommentid.sql',1425312513),('phabricator:20140214.clean.2.dropcomment.sql',1425312513),('phabricator:20140214.clean.3.dropinline.sql',1425312513),('phabricator:20140218.differentialdraft.sql',1425312513),('phabricator:20140218.passwords.1.extend.sql',1425312513),('phabricator:20140218.passwords.2.prefix.sql',1425312513),('phabricator:20140218.passwords.3.vcsextend.sql',1425312513),('phabricator:20140218.passwords.4.vcs.php',1425312513),('phabricator:20140223.bigutf8scratch.sql',1425312513),('phabricator:20140224.dxclean.1.datecommitted.sql',1425312513),('phabricator:20140226.dxcustom.1.fielddata.php',1425312513),('phabricator:20140226.dxcustom.99.drop.sql',1425312513),('phabricator:20140228.dxcomment.1.sql',1425312513),('phabricator:20140305.diviner.1.slugcol.sql',1425312513),('phabricator:20140305.diviner.2.slugkey.sql',1425312514),('phabricator:20140311.mdroplegacy.sql',1425312514),('phabricator:20140314.projectcolumn.1.statuscol.sql',1425312514),('phabricator:20140314.projectcolumn.2.statuskey.sql',1425312514),('phabricator:20140317.mupdatedkey.sql',1425312514),('phabricator:20140321.harbor.1.bxaction.sql',1425312514),('phabricator:20140321.mstatus.1.col.sql',1425312514),('phabricator:20140321.mstatus.2.mig.php',1425312514),('phabricator:20140323.harbor.1.renames.php',1425312514),('phabricator:20140323.harbor.2.message.sql',1425312514),('phabricator:20140325.push.1.event.sql',1425312514),('phabricator:20140325.push.2.eventphid.sql',1425312514),('phabricator:20140325.push.3.groups.php',1425312514),('phabricator:20140325.push.4.prune.sql',1425312514),('phabricator:20140326.project.1.colxaction.sql',1425312514),('phabricator:20140328.releeph.1.productxaction.sql',1425312514),('phabricator:20140330.flagtext.sql',1425312514),('phabricator:20140402.actionlog.sql',1425312514),('phabricator:20140410.accountsecret.1.sql',1425312514),('phabricator:20140410.accountsecret.2.php',1425312514),('phabricator:20140416.harbor.1.sql',1425312514),('phabricator:20140420.rel.1.objectphid.sql',1425312514),('phabricator:20140420.rel.2.objectmig.php',1425312514),('phabricator:20140421.slowvotecolumnsisclosed.sql',1425312514),('phabricator:20140423.session.1.hisec.sql',1425312514),('phabricator:20140427.mfactor.1.sql',1425312514),('phabricator:20140430.auth.1.partial.sql',1425312514),('phabricator:20140430.dash.1.paneltype.sql',1425312514),('phabricator:20140430.dash.2.edge.sql',1425312514),('phabricator:20140501.passphraselockcredential.sql',1425312514),('phabricator:20140501.remove.1.dlog.sql',1425312514),('phabricator:20140507.smstable.sql',1425312514),('phabricator:20140509.coverage.1.sql',1425312514),('phabricator:20140509.dashboardlayoutconfig.sql',1425312514),('phabricator:20140512.dparents.1.sql',1425312514),('phabricator:20140514.harbormasterbuildabletransaction.sql',1425312514),('phabricator:20140514.pholiomockclose.sql',1425312514),('phabricator:20140515.trust-emails.sql',1425312514),('phabricator:20140517.dxbinarycache.sql',1425312514),('phabricator:20140518.dxmorebinarycache.sql',1425312514),('phabricator:20140519.dashboardinstall.sql',1425312514),('phabricator:20140520.authtemptoken.sql',1425312514),('phabricator:20140521.projectslug.1.create.sql',1425312514),('phabricator:20140521.projectslug.2.mig.php',1425312514),('phabricator:20140522.projecticon.sql',1425312514),('phabricator:20140524.auth.mfa.cache.sql',1425312514),('phabricator:20140525.hunkmodern.sql',1425312514),('phabricator:20140615.pholioedit.1.sql',1425312514),('phabricator:20140615.pholioedit.2.sql',1425312514),('phabricator:20140617.daemon.explicit-argv.sql',1425312514),('phabricator:20140617.daemonlog.sql',1425312514),('phabricator:20140624.projcolor.1.sql',1425312514),('phabricator:20140624.projcolor.2.sql',1425312514),('phabricator:20140629.dasharchive.1.sql',1425312514),('phabricator:20140629.legalsig.1.sql',1425312514),('phabricator:20140629.legalsig.2.php',1425312514),('phabricator:20140701.legalexemption.1.sql',1425312514),('phabricator:20140701.legalexemption.2.sql',1425312514),('phabricator:20140703.legalcorp.1.sql',1425312514),('phabricator:20140703.legalcorp.2.sql',1425312514),('phabricator:20140703.legalcorp.3.sql',1425312514),('phabricator:20140703.legalcorp.4.sql',1425312514),('phabricator:20140703.legalcorp.5.sql',1425312514),('phabricator:20140704.harbormasterstep.1.sql',1425312514),('phabricator:20140704.harbormasterstep.2.sql',1425312514),('phabricator:20140704.legalpreamble.1.sql',1425312514),('phabricator:20140706.harbormasterdepend.1.php',1425312514),('phabricator:20140706.pedge.1.sql',1425312514),('phabricator:20140711.pnames.1.sql',1425312514),('phabricator:20140711.pnames.2.php',1425312514),('phabricator:20140711.workerpriority.sql',1425312515),('phabricator:20140712.projcoluniq.sql',1425312515),('phabricator:20140721.phortune.1.cart.sql',1425312515),('phabricator:20140721.phortune.2.purchase.sql',1425312515),('phabricator:20140721.phortune.3.charge.sql',1425312515),('phabricator:20140721.phortune.4.cartstatus.sql',1425312515),('phabricator:20140721.phortune.5.cstatusdefault.sql',1425312515),('phabricator:20140721.phortune.6.onetimecharge.sql',1425312515),('phabricator:20140721.phortune.7.nullmethod.sql',1425312515),('phabricator:20140722.appname.php',1425312515),('phabricator:20140722.audit.1.xactions.sql',1425312515),('phabricator:20140722.audit.2.comments.sql',1425312515),('phabricator:20140722.audit.3.miginlines.php',1425312515),('phabricator:20140722.audit.4.migtext.php',1425312515),('phabricator:20140722.renameauth.php',1425312515),('phabricator:20140723.apprenamexaction.sql',1425312515),('phabricator:20140725.audit.1.migxactions.php',1425312515),('phabricator:20140731.audit.1.subscribers.php',1425312515),('phabricator:20140731.cancdn.php',1425312515),('phabricator:20140731.harbormasterstepdesc.sql',1425312515),('phabricator:20140805.boardcol.1.sql',1425312515),('phabricator:20140805.boardcol.2.php',1425312515),('phabricator:20140807.harbormastertargettime.sql',1425312515),('phabricator:20140808.boardprop.1.sql',1425312515),('phabricator:20140808.boardprop.2.sql',1425312515),('phabricator:20140808.boardprop.3.php',1425312515),('phabricator:20140811.blob.1.sql',1425312515),('phabricator:20140811.blob.2.sql',1425312515),('phabricator:20140812.projkey.1.sql',1425312515),('phabricator:20140812.projkey.2.sql',1425312515),('phabricator:20140814.passphrasecredentialconduit.sql',1425312515),('phabricator:20140815.cancdncase.php',1425312515),('phabricator:20140818.harbormasterindex.1.sql',1425312515),('phabricator:20140821.harbormasterbuildgen.1.sql',1425312515),('phabricator:20140822.daemonenvhash.sql',1425312515),('phabricator:20140902.almanacdevice.1.sql',1425312515),('phabricator:20140904.macroattach.php',1425312515),('phabricator:20140911.fund.1.initiative.sql',1425312515),('phabricator:20140911.fund.2.xaction.sql',1425312515),('phabricator:20140911.fund.3.edge.sql',1425312515),('phabricator:20140911.fund.4.backer.sql',1425312515),('phabricator:20140911.fund.5.backxaction.sql',1425312515),('phabricator:20140914.betaproto.php',1425312515),('phabricator:20140917.project.canlock.sql',1425312515),('phabricator:20140918.schema.1.dropaudit.sql',1425312515),('phabricator:20140918.schema.2.dropauditinline.sql',1425312515),('phabricator:20140918.schema.3.wipecache.sql',1425312515),('phabricator:20140918.schema.4.cachetype.sql',1425312515),('phabricator:20140918.schema.5.slowvote.sql',1425312515),('phabricator:20140919.schema.01.calstatus.sql',1425312515),('phabricator:20140919.schema.02.calname.sql',1425312515),('phabricator:20140919.schema.03.dropaux.sql',1425312515),('phabricator:20140919.schema.04.droptaskproj.sql',1425312515),('phabricator:20140926.schema.01.droprelev.sql',1425312515),('phabricator:20140926.schema.02.droprelreqev.sql',1425312515),('phabricator:20140926.schema.03.dropldapinfo.sql',1425312515),('phabricator:20140926.schema.04.dropoauthinfo.sql',1425312515),('phabricator:20140926.schema.05.dropprojaffil.sql',1425312515),('phabricator:20140926.schema.06.dropsubproject.sql',1425312515),('phabricator:20140926.schema.07.droppondcom.sql',1425312515),('phabricator:20140927.schema.01.dropsearchq.sql',1425312515),('phabricator:20140927.schema.02.pholio1.sql',1425312515),('phabricator:20140927.schema.03.pholio2.sql',1425312515),('phabricator:20140927.schema.04.pholio3.sql',1425312515),('phabricator:20140927.schema.05.phragment1.sql',1425312515),('phabricator:20140927.schema.06.releeph1.sql',1425312515),('phabricator:20141001.schema.01.version.sql',1425312515),('phabricator:20141001.schema.02.taskmail.sql',1425312515),('phabricator:20141002.schema.01.liskcounter.sql',1425312515),('phabricator:20141002.schema.02.draftnull.sql',1425312515),('phabricator:20141004.currency.01.sql',1425312515),('phabricator:20141004.currency.02.sql',1425312515),('phabricator:20141004.currency.03.sql',1425312515),('phabricator:20141004.currency.04.sql',1425312515),('phabricator:20141004.currency.05.sql',1425312515),('phabricator:20141004.currency.06.sql',1425312515),('phabricator:20141004.harborliskcounter.sql',1425312515),('phabricator:20141005.phortuneproduct.sql',1425312515),('phabricator:20141006.phortunecart.sql',1425312515),('phabricator:20141006.phortunemerchant.sql',1425312515),('phabricator:20141006.phortunemerchantx.sql',1425312515),('phabricator:20141007.fundmerchant.sql',1425312515),('phabricator:20141007.fundrisks.sql',1425312515),('phabricator:20141007.fundtotal.sql',1425312515),('phabricator:20141007.phortunecartmerchant.sql',1425312515),('phabricator:20141007.phortunecharge.sql',1425312516),('phabricator:20141007.phortunepayment.sql',1425312516),('phabricator:20141007.phortuneprovider.sql',1425312516),('phabricator:20141007.phortuneproviderx.sql',1425312516),('phabricator:20141008.phortunemerchdesc.sql',1425312516),('phabricator:20141008.phortuneprovdis.sql',1425312516),('phabricator:20141008.phortunerefund.sql',1425312516),('phabricator:20141010.fundmailkey.sql',1425312516),('phabricator:20141011.phortunemerchedit.sql',1425312516),('phabricator:20141012.phortunecartxaction.sql',1425312516),('phabricator:20141013.phortunecartkey.sql',1425312516),('phabricator:20141016.almanac.device.sql',1425312516),('phabricator:20141016.almanac.dxaction.sql',1425312516),('phabricator:20141016.almanac.interface.sql',1425312516),('phabricator:20141016.almanac.network.sql',1425312516),('phabricator:20141016.almanac.nxaction.sql',1425312516),('phabricator:20141016.almanac.service.sql',1425312516),('phabricator:20141016.almanac.sxaction.sql',1425312516),('phabricator:20141017.almanac.binding.sql',1425312516),('phabricator:20141017.almanac.bxaction.sql',1425312516),('phabricator:20141025.phriction.1.xaction.sql',1425312516),('phabricator:20141025.phriction.2.xaction.sql',1425312516),('phabricator:20141025.phriction.mailkey.sql',1425312516),('phabricator:20141103.almanac.1.delprop.sql',1425312516),('phabricator:20141103.almanac.2.addprop.sql',1425312516),('phabricator:20141104.almanac.3.edge.sql',1425312516),('phabricator:20141105.ssh.1.rename.sql',1425312516),('phabricator:20141106.dropold.sql',1425312516),('phabricator:20141106.uniqdrafts.php',1425312516),('phabricator:20141107.phriction.policy.1.sql',1425312516),('phabricator:20141107.phriction.policy.2.php',1425312516),('phabricator:20141107.phriction.popkeys.php',1425312516),('phabricator:20141107.ssh.1.colname.sql',1425312516),('phabricator:20141107.ssh.2.keyhash.sql',1425312516),('phabricator:20141107.ssh.3.keyindex.sql',1425312516),('phabricator:20141107.ssh.4.keymig.php',1425312516),('phabricator:20141107.ssh.5.indexnull.sql',1425312516),('phabricator:20141107.ssh.6.indexkey.sql',1425312516),('phabricator:20141107.ssh.7.colnull.sql',1425312516),('phabricator:20141113.auditdupes.php',1425312516),('phabricator:20141118.diffxaction.sql',1425312516),('phabricator:20141119.commitpedge.sql',1425312516),('phabricator:20141119.differential.diff.policy.sql',1425312516),('phabricator:20141119.sshtrust.sql',1425312516),('phabricator:20141123.taskpriority.1.sql',1425312516),('phabricator:20141123.taskpriority.2.sql',1425312516),('phabricator:20141210.maniphestsubscribersmig.1.sql',1425312516),('phabricator:20141210.maniphestsubscribersmig.2.sql',1425312516),('phabricator:20141210.reposervice.sql',1425312516),('phabricator:20141212.conduittoken.sql',1425312516),('phabricator:20141215.almanacservicetype.sql',1425312516),('phabricator:20141217.almanacdevicelock.sql',1425312516),('phabricator:20141217.almanaclock.sql',1425312516),('phabricator:20141218.maniphestcctxn.php',1425312516),('phabricator:20141222.maniphestprojtxn.php',1425312516),('phabricator:20141223.daemonloguser.sql',1425312516),('phabricator:20141223.daemonobjectphid.sql',1425312516),('phabricator:20141230.pasteeditpolicycolumn.sql',1425312516),('phabricator:20141230.pasteeditpolicyexisting.sql',1425312516),('phabricator:20150102.policyname.php',1425312516),('phabricator:20150102.tasksubscriber.sql',1425312516),('phabricator:20150105.conpsearch.sql',1425312516),('phabricator:20150114.oauthserver.client.policy.sql',1425312517),('phabricator:20150115.applicationemails.sql',1425312517),('phabricator:20150115.trigger.1.sql',1425312517),('phabricator:20150115.trigger.2.sql',1425312517),('phabricator:20150116.maniphestapplicationemails.php',1425312517),('phabricator:20150120.maniphestdefaultauthor.php',1425312517),('phabricator:20150124.subs.1.sql',1425312517),('phabricator:20150129.pastefileapplicationemails.php',1425312517),('phabricator:20150130.phortune.1.subphid.sql',1425312517),('phabricator:20150130.phortune.2.subkey.sql',1425312517),('phabricator:20150131.phortune.1.defaultpayment.sql',1425312517),('phabricator:20150205.authprovider.autologin.sql',1425312517),('phabricator:20150205.daemonenv.sql',1425312517),('phabricator:20150209.invite.sql',1425312517),('phabricator:20150209.oauthclient.trust.sql',1425312517),('phabricator:20150210.invitephid.sql',1425312517),('phabricator:20150212.legalpad.session.1.sql',1425312517),('phabricator:20150212.legalpad.session.2.sql',1425312517),('phabricator:20150219.scratch.nonmutable.sql',1425312517),('phabricator:20150223.daemon.1.id.sql',1425312517),('phabricator:20150223.daemon.2.idlegacy.sql',1425312517),('phabricator:20150223.daemon.3.idkey.sql',1425312517),('phabricator:daemonstatus.sql',1425312509),('phabricator:daemonstatuskey.sql',1425312509),('phabricator:daemontaskarchive.sql',1425312509),('phabricator:db.almanac',1425312504),('phabricator:db.audit',1425312504),('phabricator:db.auth',1425312504),('phabricator:db.cache',1425312504),('phabricator:db.calendar',1425312504),('phabricator:db.chatlog',1425312504),('phabricator:db.conduit',1425312504),('phabricator:db.config',1425312504),('phabricator:db.conpherence',1425312504),('phabricator:db.countdown',1425312504),('phabricator:db.daemon',1425312504),('phabricator:db.dashboard',1425312504),('phabricator:db.differential',1425312504),('phabricator:db.diviner',1425312504),('phabricator:db.doorkeeper',1425312504),('phabricator:db.draft',1425312504),('phabricator:db.drydock',1425312504),('phabricator:db.fact',1425312504),('phabricator:db.feed',1425312504),('phabricator:db.file',1425312504),('phabricator:db.flag',1425312504),('phabricator:db.fund',1425312504),('phabricator:db.harbormaster',1425312504),('phabricator:db.herald',1425312504),('phabricator:db.legalpad',1425312504),('phabricator:db.maniphest',1425312504),('phabricator:db.meta_data',1425312504),('phabricator:db.metamta',1425312504),('phabricator:db.nuance',1425312504),('phabricator:db.oauth_server',1425312504),('phabricator:db.owners',1425312504),('phabricator:db.passphrase',1425312504),('phabricator:db.pastebin',1425312504),('phabricator:db.phame',1425312504),('phabricator:db.phlux',1425312504),('phabricator:db.pholio',1425312504),('phabricator:db.phortune',1425312504),('phabricator:db.phragment',1425312504),('phabricator:db.phrequent',1425312504),('phabricator:db.phriction',1425312504),('phabricator:db.policy',1425312504),('phabricator:db.ponder',1425312504),('phabricator:db.project',1425312504),('phabricator:db.releeph',1425312504),('phabricator:db.repository',1425312504),('phabricator:db.search',1425312504),('phabricator:db.slowvote',1425312504),('phabricator:db.system',1425312504),('phabricator:db.timeline',1425312504),('phabricator:db.token',1425312504),('phabricator:db.user',1425312504),('phabricator:db.worker',1425312504),('phabricator:db.xhpastview',1425312504),('phabricator:db.xhprof',1425312504),('phabricator:differentialbookmarks.sql',1425312509),('phabricator:draft-metadata.sql',1425312509),('phabricator:dropfileproxyimage.sql',1425312509),('phabricator:drydockresoucetype.sql',1425312509),('phabricator:drydocktaskid.sql',1425312509),('phabricator:edgetype.sql',1425312509),('phabricator:emailtable.sql',1425312509),('phabricator:emailtableport.sql',1425312509),('phabricator:emailtableremove.sql',1425312509),('phabricator:fact-raw.sql',1425312509),('phabricator:harbormasterobject.sql',1425312509),('phabricator:holidays.sql',1425312509),('phabricator:ldapinfo.sql',1425312509),('phabricator:legalpad-mailkey-populate.php',1425312511),('phabricator:legalpad-mailkey.sql',1425312511),('phabricator:liskcounters-task.sql',1425312509),('phabricator:liskcounters.php',1425312509),('phabricator:liskcounters.sql',1425312509),('phabricator:maniphestxcache.sql',1425312509),('phabricator:markupcache.sql',1425312509),('phabricator:migrate-differential-dependencies.php',1425312509),('phabricator:migrate-maniphest-dependencies.php',1425312509),('phabricator:migrate-maniphest-revisions.php',1425312509),('phabricator:migrate-project-edges.php',1425312509),('phabricator:owners-exclude.sql',1425312509),('phabricator:pastepolicy.sql',1425312509),('phabricator:phameblog.sql',1425312509),('phabricator:phamedomain.sql',1425312509),('phabricator:phameoneblog.sql',1425312509),('phabricator:phamepolicy.sql',1425312509),('phabricator:phiddrop.sql',1425312509),('phabricator:pholio.sql',1425312509),('phabricator:policy-project.sql',1425312509),('phabricator:ponder-comments.sql',1425312509),('phabricator:ponder-mailkey-populate.php',1425312509),('phabricator:ponder-mailkey.sql',1425312509),('phabricator:ponder.sql',1425312509),('phabricator:releeph.sql',1425312510),('phabricator:repository-lint.sql',1425312509),('phabricator:statustxt.sql',1425312509),('phabricator:symbolcontexts.sql',1425312509),('phabricator:testdatabase.sql',1425312509),('phabricator:threadtopic.sql',1425312509),('phabricator:userstatus.sql',1425312509),('phabricator:usertranslation.sql',1425312509),('phabricator:xhprof.sql',1425312509);

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_metamta` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_metamta`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `metamta_applicationemail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `applicationPHID` varbinary(64) NOT NULL,
  `address` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `configData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_address` (`address`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_application` (`applicationPHID`)
) ENGINE=MyISAM DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `metamta_mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `message` longtext COLLATE {$COLLATE_TEXT},
  `relatedPHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `relatedPHID` (`relatedPHID`),
  KEY `key_created` (`dateCreated`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `metamta_mailinglist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `email` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `uri` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `metamta_receivedmail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `headers` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `bodies` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `attachments` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `relatedPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `message` longtext COLLATE {$COLLATE_TEXT},
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `messageIDHash` binary(12) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `relatedPHID` (`relatedPHID`),
  KEY `authorPHID` (`authorPHID`),
  KEY `key_messageIDHash` (`messageIDHash`),
  KEY `key_created` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `sms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `providerShortName` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `providerSMSID` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  `toNumber` varchar(20) COLLATE {$COLLATE_TEXT} NOT NULL,
  `fromNumber` varchar(20) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `body` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `sendStatus` varchar(16) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_provider` (`providerSMSID`,`providerShortName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_oauth_server` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_oauth_server`;

CREATE TABLE `oauth_server_oauthclientauthorization` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `userPHID` varbinary(64) NOT NULL,
  `clientPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `scope` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `userPHID` (`userPHID`,`clientPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `oauth_server_oauthserveraccesstoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `userPHID` varbinary(64) NOT NULL,
  `clientPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `oauth_server_oauthserverauthorizationcode` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `clientPHID` varbinary(64) NOT NULL,
  `clientSecret` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `userPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `redirectURI` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `oauth_server_oauthserverclient` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `secret` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `redirectURI` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `creatorPHID` varbinary(64) NOT NULL,
  `isTrusted` tinyint(1) NOT NULL DEFAULT '0',
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `creatorPHID` (`creatorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_owners` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_owners`;

CREATE TABLE `owners_owner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packageID` int(10) unsigned NOT NULL,
  `userPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `packageID` (`packageID`,`userPHID`),
  KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `owners_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `originalName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `primaryOwnerPHID` varbinary(64) DEFAULT NULL,
  `auditingEnabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `owners_path` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packageID` int(10) unsigned NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `path` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `excluded` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `packageID` (`packageID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_pastebin` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_pastebin`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `pastebin_paste` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `filePHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `language` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parentPHID` varbinary(64) DEFAULT NULL,
  `viewPolicy` varbinary(64) DEFAULT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `parentPHID` (`parentPHID`),
  KEY `authorPHID` (`authorPHID`),
  KEY `key_dateCreated` (`dateCreated`),
  KEY `key_language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `pastebin_pastetransaction` (
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

CREATE TABLE `pastebin_pastetransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `lineNumber` int(10) unsigned DEFAULT NULL,
  `lineLength` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phame` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phame`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phame_blog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `domain` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `configData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `creatorPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `viewPolicy` varbinary(64) DEFAULT NULL,
  `editPolicy` varbinary(64) DEFAULT NULL,
  `joinPolicy` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phame_post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `bloggerPHID` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phameTitle` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `body` longtext COLLATE {$COLLATE_TEXT},
  `visibility` int(10) unsigned NOT NULL DEFAULT '0',
  `configData` longtext COLLATE {$COLLATE_TEXT},
  `datePublished` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `blogPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `phameTitle` (`bloggerPHID`,`phameTitle`),
  KEY `bloggerPosts` (`bloggerPHID`,`visibility`,`datePublished`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phriction` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phriction`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phriction_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentID` int(10) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `title` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `slug` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT},
  `changeType` int(10) unsigned NOT NULL DEFAULT '0',
  `changeRef` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documentID` (`documentID`,`version`),
  KEY `authorPHID` (`authorPHID`),
  KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phriction_document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `slug` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `depth` int(10) unsigned NOT NULL,
  `contentID` int(10) unsigned DEFAULT NULL,
  `status` int(10) unsigned NOT NULL DEFAULT '0',
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `depth` (`depth`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phriction_transaction` (
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

CREATE TABLE `phriction_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_project` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_project`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `subprojectPHIDs` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `phrictionSlug` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `viewPolicy` varbinary(64) DEFAULT NULL,
  `editPolicy` varbinary(64) DEFAULT NULL,
  `joinPolicy` varbinary(64) DEFAULT NULL,
  `isMembershipLocked` tinyint(1) NOT NULL DEFAULT '0',
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `icon` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `color` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `phrictionSlug` (`phrictionSlug`),
  KEY `key_icon` (`icon`),
  KEY `key_color` (`color`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_column` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `projectPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_status` (`projectPHID`,`status`,`sequence`),
  KEY `key_sequence` (`projectPHID`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_columnposition` (
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

CREATE TABLE `project_columntransaction` (
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

CREATE TABLE `project_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_datasourcetoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectID` int(10) unsigned NOT NULL,
  `token` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`,`projectID`),
  KEY `projectID` (`projectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_slug` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectPHID` varbinary(64) NOT NULL,
  `slug` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_slug` (`slug`),
  KEY `key_projectPHID` (`projectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `project_transaction` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_repository` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_repository`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `callsign` varchar(32) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `versionControlSystem` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `uuid` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `pushPolicy` varbinary(64) NOT NULL,
  `credentialPHID` varbinary(64) DEFAULT NULL,
  `almanacServicePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `callsign` (`callsign`),
  UNIQUE KEY `phid` (`phid`),
  KEY `key_vcs` (`versionControlSystem`),
  KEY `key_name` (`name`(128))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_arcanistproject` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `repositoryID` int(10) unsigned DEFAULT NULL,
  `symbolIndexLanguages` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `symbolIndexProjects` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_auditrequest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auditorPHID` varbinary(64) NOT NULL,
  `commitPHID` varbinary(64) NOT NULL,
  `auditStatus` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `auditReasons` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_unique` (`commitPHID`,`auditorPHID`),
  KEY `commitPHID` (`commitPHID`),
  KEY `auditorPHID` (`auditorPHID`,`auditStatus`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_badcommit` (
  `fullCommitName` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`fullCommitName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_branch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `lintCommit` varchar(40) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repositoryID` (`repositoryID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_commit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `commitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `auditStatus` int(10) unsigned NOT NULL,
  `summary` varchar(80) COLLATE {$COLLATE_TEXT} NOT NULL,
  `importStatus` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_commit_identity` (`commitIdentifier`,`repositoryID`),
  KEY `repositoryID_2` (`repositoryID`,`epoch`),
  KEY `authorPHID` (`authorPHID`,`auditStatus`,`epoch`),
  KEY `repositoryID` (`repositoryID`,`importStatus`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_commitdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commitID` int(10) unsigned NOT NULL,
  `authorName` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `commitMessage` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `commitDetails` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commitID` (`commitID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_coverage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branchID` int(10) unsigned NOT NULL,
  `commitID` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `coverage` longblob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_path` (`branchID`,`pathID`,`commitID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_filesystem` (
  `repositoryID` int(10) unsigned NOT NULL,
  `parentID` int(10) unsigned NOT NULL,
  `svnCommit` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `existed` tinyint(1) NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  PRIMARY KEY (`repositoryID`,`parentID`,`pathID`,`svnCommit`),
  KEY `repositoryID` (`repositoryID`,`svnCommit`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_lintmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branchID` int(10) unsigned NOT NULL,
  `path` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `line` int(10) unsigned NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `code` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `severity` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `branchID` (`branchID`,`path`(64)),
  KEY `branchID_2` (`branchID`,`code`,`path`(64)),
  KEY `key_author` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_mirror` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `remoteURI` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `credentialPHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_repository` (`repositoryPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_parents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `childCommitID` int(10) unsigned NOT NULL,
  `parentCommitID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_child` (`childCommitID`,`parentCommitID`),
  KEY `key_parent` (`parentCommitID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_path` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `path` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `pathHash` binary(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pathHash` (`pathHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

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
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_pushevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `pusherPHID` varbinary(64) NOT NULL,
  `remoteAddress` int(10) unsigned DEFAULT NULL,
  `remoteProtocol` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `rejectCode` int(10) unsigned NOT NULL,
  `rejectDetails` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_repository` (`repositoryPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_pushlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `pushEventPHID` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `pusherPHID` varbinary(64) NOT NULL,
  `refType` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  `refNameHash` binary(12) DEFAULT NULL,
  `refNameRaw` longblob,
  `refNameEncoding` varchar(16) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `refOld` varchar(40) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `refNew` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  `mergeBase` varchar(40) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `changeFlags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_repository` (`repositoryPHID`),
  KEY `key_ref` (`repositoryPHID`,`refNew`),
  KEY `key_pusher` (`pusherPHID`),
  KEY `key_name` (`repositoryPHID`,`refNameHash`),
  KEY `key_event` (`pushEventPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_refcursor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varbinary(64) NOT NULL,
  `refType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `refNameHash` binary(12) NOT NULL,
  `refNameRaw` longblob NOT NULL,
  `refNameEncoding` varchar(16) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `commitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_cursor` (`repositoryPHID`,`refType`,`refNameHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_statusmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `statusType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `statusCode` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repositoryID` (`repositoryID`,`statusType`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_summary` (
  `repositoryID` int(10) unsigned NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `lastCommitID` int(10) unsigned NOT NULL,
  `epoch` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`repositoryID`),
  KEY `key_epoch` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_symbol` (
  `arcanistProjectID` int(10) unsigned NOT NULL,
  `symbolContext` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `symbolName` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `symbolType` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  `symbolLanguage` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  KEY `symbolName` (`symbolName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_transaction` (
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
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_vcspassword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `passwordHash` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_search` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_search`;

CREATE TABLE `search_document` (
  `phid` varbinary(64) NOT NULL,
  `documentType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `documentTitle` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `documentCreated` int(10) unsigned NOT NULL,
  `documentModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`phid`),
  KEY `documentCreated` (`documentCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_documentfield` (
  `phid` varbinary(64) NOT NULL,
  `phidType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `field` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `auxPHID` varbinary(64) DEFAULT NULL,
  `corpus` longtext CHARACTER SET {$CHARSET_FULLTEXT} COLLATE {$COLLATE_FULLTEXT},
  KEY `phid` (`phid`),
  FULLTEXT KEY `corpus` (`corpus`)
) ENGINE=MyISAM DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_documentrelationship` (
  `phid` varbinary(64) NOT NULL,
  `relatedPHID` varbinary(64) NOT NULL,
  `relation` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `relatedType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `relatedTime` int(10) unsigned NOT NULL,
  KEY `phid` (`phid`),
  KEY `relatedPHID` (`relatedPHID`,`relation`),
  KEY `relation` (`relation`,`relatedPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_namedquery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `engineClassName` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `queryName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `queryKey` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isBuiltin` tinyint(1) NOT NULL DEFAULT '0',
  `isDisabled` tinyint(1) NOT NULL DEFAULT '0',
  `sequence` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_userquery` (`userPHID`,`engineClassName`,`queryKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_savedquery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `engineClassName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `queryKey` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_queryKey` (`queryKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_slowvote` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_slowvote`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `slowvote_choice` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pollID` int(10) unsigned NOT NULL,
  `optionID` int(10) unsigned NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pollID` (`pollID`),
  KEY `authorPHID` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `slowvote_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pollID` int(10) unsigned NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pollID` (`pollID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `slowvote_poll` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `responseVisibility` int(10) unsigned NOT NULL,
  `shuffle` int(10) unsigned NOT NULL,
  `method` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `isClosed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `slowvote_transaction` (
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

CREATE TABLE `slowvote_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_user` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_user`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phabricator_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `type` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `sessionKey` binary(40) NOT NULL,
  `sessionStart` int(10) unsigned NOT NULL,
  `sessionExpires` int(10) unsigned NOT NULL,
  `highSecurityUntil` int(10) unsigned DEFAULT NULL,
  `isPartial` tinyint(1) NOT NULL DEFAULT '0',
  `signedLegalpadDocuments` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sessionKey` (`sessionKey`),
  KEY `key_identity` (`userPHID`,`type`),
  KEY `key_expires` (`sessionExpires`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `userName` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `realName` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `sex` varchar(4) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `translation` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `passwordSalt` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `passwordHash` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `consoleEnabled` tinyint(1) NOT NULL,
  `consoleVisible` tinyint(1) NOT NULL,
  `consoleTab` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `conduitCertificate` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isSystemAgent` tinyint(1) NOT NULL DEFAULT '0',
  `isDisabled` tinyint(1) NOT NULL,
  `isAdmin` tinyint(1) NOT NULL,
  `timezoneIdentifier` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isEmailVerified` int(10) unsigned NOT NULL,
  `isApproved` int(10) unsigned NOT NULL,
  `accountSecret` binary(64) NOT NULL,
  `isEnrolledInMultiFactor` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userName` (`userName`),
  UNIQUE KEY `phid` (`phid`),
  KEY `realName` (`realName`),
  KEY `key_approved` (`isApproved`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_authinvite` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) NOT NULL,
  `emailAddress` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `verificationHash` binary(12) NOT NULL,
  `acceptedByPHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_address` (`emailAddress`),
  UNIQUE KEY `key_code` (`verificationHash`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_configuredcustomfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_email` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `address` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `isVerified` tinyint(1) NOT NULL DEFAULT '0',
  `isPrimary` tinyint(1) NOT NULL DEFAULT '0',
  `verificationCode` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`),
  KEY `userPHID` (`userPHID`,`isPrimary`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_externalaccount` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `userPHID` varbinary(64) DEFAULT NULL,
  `accountType` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `accountDomain` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `accountSecret` longtext COLLATE {$COLLATE_TEXT},
  `accountID` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `displayName` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `username` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `realName` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `email` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `emailVerified` tinyint(1) NOT NULL,
  `accountURI` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `account_details` (`accountType`,`accountDomain`,`accountID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actorPHID` varbinary(64) DEFAULT NULL,
  `userPHID` varbinary(64) NOT NULL,
  `action` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `oldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `newValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `remoteAddr` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `session` binary(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `actorPHID` (`actorPHID`,`dateCreated`),
  KEY `userPHID` (`userPHID`,`dateCreated`),
  KEY `action` (`action`,`dateCreated`),
  KEY `dateCreated` (`dateCreated`),
  KEY `remoteAddr` (`remoteAddr`,`dateCreated`),
  KEY `session` (`session`,`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_nametoken` (
  `token` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `userID` int(10) unsigned NOT NULL,
  KEY `token` (`token`(128)),
  KEY `userID` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `preferences` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `blurb` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_transaction` (
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
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_worker` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_worker`;

CREATE TABLE `lisk_counter` (
  `counterName` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `counterValue` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`counterName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

INSERT INTO `lisk_counter` VALUES ('worker_activetask',2);

CREATE TABLE `worker_activetask` (
  `id` int(10) unsigned NOT NULL,
  `taskClass` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `leaseOwner` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `leaseExpires` int(10) unsigned DEFAULT NULL,
  `failureCount` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  `failureTime` int(10) unsigned DEFAULT NULL,
  `priority` int(10) unsigned NOT NULL,
  `objectPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dataID` (`dataID`),
  KEY `leaseExpires` (`leaseExpires`),
  KEY `leaseOwner` (`leaseOwner`(16)),
  KEY `key_failuretime` (`failureTime`),
  KEY `taskClass` (`taskClass`),
  KEY `leaseOwner_2` (`leaseOwner`,`priority`,`id`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `worker_archivetask` (
  `id` int(10) unsigned NOT NULL,
  `taskClass` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `leaseOwner` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `leaseExpires` int(10) unsigned DEFAULT NULL,
  `failureCount` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned NOT NULL,
  `result` int(10) unsigned NOT NULL,
  `duration` bigint(20) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `priority` int(10) unsigned NOT NULL,
  `objectPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dateCreated` (`dateCreated`),
  KEY `leaseOwner` (`leaseOwner`,`priority`,`id`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `worker_taskdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `worker_trigger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `triggerVersion` int(10) unsigned NOT NULL,
  `clockClass` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `clockProperties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `actionClass` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `actionProperties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_trigger` (`triggerVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `worker_triggerevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `triggerID` int(10) unsigned NOT NULL,
  `lastEventEpoch` int(10) unsigned DEFAULT NULL,
  `nextEventEpoch` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_trigger` (`triggerID`),
  KEY `key_next` (`nextEventEpoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_xhpastview` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_xhpastview`;

CREATE TABLE `xhpastview_parsetree` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `input` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `stdout` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_cache` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_cache`;

CREATE TABLE `cache_general` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cacheKeyHash` binary(12) NOT NULL,
  `cacheKey` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `cacheFormat` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `cacheData` longblob NOT NULL,
  `cacheCreated` int(10) unsigned NOT NULL,
  `cacheExpires` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_cacheKeyHash` (`cacheKeyHash`),
  KEY `key_cacheCreated` (`cacheCreated`),
  KEY `key_ttl` (`cacheExpires`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `cache_markupcache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cacheKey` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `cacheData` longblob NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cacheKey` (`cacheKey`),
  KEY `dateCreated` (`dateCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_fact` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_fact`;

CREATE TABLE `fact_aggregate` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `factType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `valueX` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `factType` (`factType`,`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `fact_cursor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `position` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `fact_raw` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `factType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `objectA` varbinary(64) NOT NULL,
  `valueX` bigint(20) NOT NULL,
  `valueY` bigint(20) NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `objectPHID` (`objectPHID`),
  KEY `factType` (`factType`,`epoch`),
  KEY `factType_2` (`factType`,`objectA`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_ponder` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_ponder`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `ponder_answer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `questionID` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `voteCount` int(10) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT},
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_oneanswerperquestion` (`questionID`,`authorPHID`),
  KEY `questionID` (`questionID`),
  KEY `authorPHID` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `ponder_answertransaction` (
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

CREATE TABLE `ponder_answertransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `ponder_question` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `voteCount` int(10) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT},
  `heat` double NOT NULL,
  `answerCount` int(10) unsigned NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`),
  KEY `heat` (`heat`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `ponder_questiontransaction` (
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

CREATE TABLE `ponder_questiontransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_xhprof` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_xhprof`;

CREATE TABLE `xhprof_sample` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filePHID` varbinary(64) NOT NULL,
  `sampleRate` int(10) unsigned NOT NULL,
  `usTotal` bigint(20) unsigned NOT NULL,
  `hostname` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `requestPath` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `controller` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `userPHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filePHID` (`filePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_pholio` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_pholio`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `pholio_image` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `mockID` int(10) unsigned DEFAULT NULL,
  `filePHID` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isObsolete` tinyint(1) NOT NULL DEFAULT '0',
  `replacesImagePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyPHID` (`phid`),
  KEY `mockID` (`mockID`,`isObsolete`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `pholio_mock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `originalName` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `coverPHID` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `pholio_transaction` (
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
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `pholio_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `imageID` int(10) unsigned DEFAULT NULL,
  `x` int(10) unsigned DEFAULT NULL,
  `y` int(10) unsigned DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`imageID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_conpherence` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_conpherence`;

CREATE TABLE `conpherence_index` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `threadPHID` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) NOT NULL,
  `previousTransactionPHID` varbinary(64) DEFAULT NULL,
  `corpus` longtext CHARACTER SET {$CHARSET_FULLTEXT} COLLATE {$COLLATE_FULLTEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_transaction` (`transactionPHID`),
  UNIQUE KEY `key_previous` (`previousTransactionPHID`),
  KEY `key_thread` (`threadPHID`),
  FULLTEXT KEY `key_corpus` (`corpus`)
) ENGINE=MyISAM DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conpherence_participant` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `participantPHID` varbinary(64) NOT NULL,
  `conpherencePHID` varbinary(64) NOT NULL,
  `participationStatus` int(10) unsigned NOT NULL DEFAULT '0',
  `dateTouched` int(10) unsigned NOT NULL,
  `behindTransactionPHID` varbinary(64) NOT NULL,
  `seenMessageCount` bigint(20) unsigned NOT NULL,
  `settings` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conpherencePHID` (`conpherencePHID`,`participantPHID`),
  KEY `unreadCount` (`participantPHID`,`participationStatus`),
  KEY `participationIndex` (`participantPHID`,`dateTouched`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conpherence_thread` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `messageCount` bigint(20) unsigned NOT NULL,
  `recentParticipantPHIDs` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` varchar(20) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conpherence_transaction` (
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
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conpherence_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `conpherencePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`conpherencePHID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_config` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_config`;

CREATE TABLE `config_entry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `namespace` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `configKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `value` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`namespace`,`configKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `config_transaction` (
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
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_token` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_token`;

CREATE TABLE `token_count` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `tokenCount` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_objectPHID` (`objectPHID`),
  KEY `key_count` (`tokenCount`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `token_given` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `tokenPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_all` (`objectPHID`,`authorPHID`),
  KEY `key_author` (`authorPHID`),
  KEY `key_token` (`tokenPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_releeph` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_releeph`;

CREATE TABLE `releeph_branch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `basename` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `releephProjectID` int(10) unsigned NOT NULL,
  `createdByUserPHID` varbinary(64) NOT NULL,
  `cutPointCommitPHID` varbinary(64) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `symbolicName` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `releephProjectID_2` (`releephProjectID`,`basename`),
  UNIQUE KEY `releephProjectID_name` (`releephProjectID`,`name`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `releephProjectID` (`releephProjectID`,`symbolicName`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `releeph_branchtransaction` (
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

CREATE TABLE `releeph_producttransaction` (
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

CREATE TABLE `releeph_project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `trunkBranch` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `arcanistProjectID` int(10) unsigned NOT NULL,
  `createdByUserPHID` varbinary(64) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectName` (`name`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `releeph_request` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `branchID` int(10) unsigned NOT NULL,
  `requestUserPHID` varbinary(64) NOT NULL,
  `requestCommitPHID` varbinary(64) DEFAULT NULL,
  `commitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `commitPHID` varbinary(64) DEFAULT NULL,
  `pickStatus` int(10) unsigned DEFAULT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `userIntents` longtext COLLATE {$COLLATE_TEXT},
  `inBranch` tinyint(1) NOT NULL DEFAULT '0',
  `mailKey` binary(20) NOT NULL,
  `requestedObjectPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `requestIdentifierBranch` (`requestCommitPHID`,`branchID`),
  KEY `branchID` (`branchID`),
  KEY `key_requestedObject` (`requestedObjectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `releeph_requesttransaction` (
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
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `releeph_requesttransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phlux` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phlux`;

CREATE TABLE `phlux_transaction` (
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
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phlux_variable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `variableKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `variableValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_key` (`variableKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phortune` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phortune`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_account` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_accounttransaction` (
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

CREATE TABLE `phortune_cart` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `accountPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `cartClass` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `merchantPHID` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `subscriptionPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_account` (`accountPHID`),
  KEY `key_merchant` (`merchantPHID`),
  KEY `key_subscription` (`subscriptionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_carttransaction` (
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

CREATE TABLE `phortune_charge` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `accountPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `cartPHID` varbinary(64) NOT NULL,
  `paymentMethodPHID` varbinary(64) DEFAULT NULL,
  `amountAsCurrency` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `merchantPHID` varbinary(64) NOT NULL,
  `providerPHID` varbinary(64) NOT NULL,
  `amountRefundedAsCurrency` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `refundingPHID` varbinary(64) DEFAULT NULL,
  `refundedChargePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_cart` (`cartPHID`),
  KEY `key_account` (`accountPHID`),
  KEY `key_merchant` (`merchantPHID`),
  KEY `key_provider` (`providerPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_merchant` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_merchanttransaction` (
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

CREATE TABLE `phortune_paymentmethod` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `accountPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `brand` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `expires` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `lastFourDigits` varchar(16) COLLATE {$COLLATE_TEXT} NOT NULL,
  `merchantPHID` varbinary(64) NOT NULL,
  `providerPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_account` (`accountPHID`,`status`),
  KEY `key_merchant` (`merchantPHID`,`accountPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_paymentproviderconfig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `merchantPHID` varbinary(64) NOT NULL,
  `providerClassKey` binary(12) NOT NULL,
  `providerClass` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isEnabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_merchant` (`merchantPHID`,`providerClassKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_paymentproviderconfigtransaction` (
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

CREATE TABLE `phortune_product` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `productClassKey` binary(12) NOT NULL,
  `productClass` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `productRefKey` binary(12) NOT NULL,
  `productRef` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_product` (`productClassKey`,`productRefKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_purchase` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `productPHID` varbinary(64) NOT NULL,
  `accountPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `cartPHID` varbinary(64) DEFAULT NULL,
  `basePriceAsCurrency` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_cart` (`cartPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phortune_subscription` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `accountPHID` varbinary(64) NOT NULL,
  `merchantPHID` varbinary(64) NOT NULL,
  `triggerPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `subscriptionClassKey` binary(12) NOT NULL,
  `subscriptionClass` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `subscriptionRefKey` binary(12) NOT NULL,
  `subscriptionRef` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `defaultPaymentMethodPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_subscription` (`subscriptionClassKey`,`subscriptionRefKey`),
  KEY `key_account` (`accountPHID`),
  KEY `key_merchant` (`merchantPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phrequent` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phrequent`;

CREATE TABLE `phrequent_usertime` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) DEFAULT NULL,
  `note` longtext COLLATE {$COLLATE_TEXT},
  `dateStarted` int(10) unsigned NOT NULL,
  `dateEnded` int(10) unsigned DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_diviner` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_diviner`;

CREATE TABLE `diviner_liveatom` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `symbolPHID` varbinary(64) NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `atomData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symbolPHID` (`symbolPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `diviner_livebook` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `configurationData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `diviner_livesymbol` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `bookPHID` varbinary(64) NOT NULL,
  `context` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `type` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `atomIndex` int(10) unsigned NOT NULL,
  `identityHash` binary(12) NOT NULL,
  `graphHash` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `title` longtext COLLATE {$COLLATE_TEXT},
  `titleSlugHash` binary(12) DEFAULT NULL,
  `groupName` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `summary` longtext COLLATE {$COLLATE_TEXT},
  `isDocumentable` tinyint(1) NOT NULL,
  `nodeHash` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identityHash` (`identityHash`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `graphHash` (`graphHash`),
  UNIQUE KEY `nodeHash` (`nodeHash`),
  KEY `key_slug` (`titleSlugHash`),
  KEY `bookPHID` (`bookPHID`,`type`,`name`(64),`context`(64),`atomIndex`),
  KEY `name` (`name`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_auth` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_auth`;

CREATE TABLE `auth_factorconfig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `userPHID` varbinary(64) NOT NULL,
  `factorKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `factorName` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `factorSecret` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_user` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `auth_providerconfig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `providerClass` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `providerType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `providerDomain` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isEnabled` tinyint(1) NOT NULL,
  `shouldAllowLogin` tinyint(1) NOT NULL,
  `shouldAllowRegistration` tinyint(1) NOT NULL,
  `shouldAllowLink` tinyint(1) NOT NULL,
  `shouldAllowUnlink` tinyint(1) NOT NULL,
  `shouldTrustEmails` tinyint(1) NOT NULL DEFAULT '0',
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `shouldAutoLogin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_provider` (`providerType`,`providerDomain`),
  KEY `key_class` (`providerClass`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `auth_providerconfigtransaction` (
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
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `auth_sshkey` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyType` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyBody` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyComment` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `keyIndex` binary(12) NOT NULL,
  `isTrusted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_unique` (`keyIndex`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `auth_temporarytoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `tokenType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `tokenExpires` int(10) unsigned NOT NULL,
  `tokenCode` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_token` (`objectPHID`,`tokenType`,`tokenCode`),
  KEY `key_expires` (`tokenExpires`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_doorkeeper` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_doorkeeper`;

CREATE TABLE `doorkeeper_externalobject` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `objectKey` binary(12) NOT NULL,
  `applicationType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `applicationDomain` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectID` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectURI` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `importerPHID` varbinary(64) DEFAULT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_object` (`objectKey`),
  KEY `key_full` (`applicationType`,`applicationDomain`,`objectType`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_legalpad` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_legalpad`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `legalpad_document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `contributorCount` int(10) unsigned NOT NULL DEFAULT '0',
  `recentContributorPHIDs` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `creatorPHID` varbinary(64) NOT NULL,
  `versions` int(10) unsigned NOT NULL DEFAULT '0',
  `documentBodyPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `signatureType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `preamble` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `requireSignature` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_creator` (`creatorPHID`,`dateModified`),
  KEY `key_required` (`requireSignature`,`dateModified`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `legalpad_documentbody` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `creatorPHID` varbinary(64) NOT NULL,
  `documentPHID` varbinary(64) NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `text` longtext COLLATE {$COLLATE_TEXT},
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_document` (`documentPHID`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `legalpad_documentsignature` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `documentPHID` varbinary(64) NOT NULL,
  `documentVersion` int(10) unsigned NOT NULL DEFAULT '0',
  `signatureType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `signerPHID` varbinary(64) DEFAULT NULL,
  `signerName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `signerEmail` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `signatureData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `secretKey` binary(20) NOT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `isExemption` tinyint(1) NOT NULL DEFAULT '0',
  `exemptionPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `key_signer` (`signerPHID`,`dateModified`),
  KEY `secretKey` (`secretKey`),
  KEY `key_document` (`documentPHID`,`signerPHID`,`documentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `legalpad_transaction` (
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

CREATE TABLE `legalpad_transaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `documentID` int(10) unsigned DEFAULT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `fixedState` varchar(12) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `hasReplies` tinyint(1) NOT NULL,
  `replyToCommentPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`),
  UNIQUE KEY `key_draft` (`authorPHID`,`documentID`,`transactionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_policy` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_policy`;

CREATE TABLE `policy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `rules` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `defaultAction` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_nuance` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_nuance`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `requestorPHID` varbinary(64) NOT NULL,
  `sourcePHID` varbinary(64) NOT NULL,
  `sourceLabel` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `status` int(10) unsigned NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `dateNuanced` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_source` (`sourcePHID`,`status`,`dateNuanced`,`id`),
  KEY `key_owner` (`ownerPHID`,`status`,`dateNuanced`,`id`),
  KEY `key_contacter` (`requestorPHID`,`status`,`dateNuanced`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_itemtransaction` (
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

CREATE TABLE `nuance_itemtransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_queueitem` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `queuePHID` varbinary(64) NOT NULL,
  `itemPHID` varbinary(64) NOT NULL,
  `itemStatus` int(10) unsigned NOT NULL,
  `itemDateNuanced` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_one_per_queue` (`itemPHID`,`queuePHID`),
  KEY `key_queue` (`queuePHID`,`itemStatus`,`itemDateNuanced`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_queuetransaction` (
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

CREATE TABLE `nuance_queuetransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_requestor` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_requestorsource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `requestorPHID` varbinary(64) NOT NULL,
  `sourcePHID` varbinary(64) NOT NULL,
  `sourceKey` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_source_key` (`sourcePHID`,`sourceKey`),
  KEY `key_requestor` (`requestorPHID`,`id`),
  KEY `key_source` (`sourcePHID`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_requestortransaction` (
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

CREATE TABLE `nuance_requestortransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `type` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_type` (`type`,`dateModified`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_sourcetransaction` (
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

CREATE TABLE `nuance_sourcetransaction_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `transactionPHID` varbinary(64) DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `commentVersion` int(10) unsigned NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDeleted` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_passphrase` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_passphrase`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `passphrase_credential` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `credentialType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `providesType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `username` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `secretID` int(10) unsigned DEFAULT NULL,
  `isDestroyed` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isLocked` tinyint(1) NOT NULL,
  `allowConduit` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_secret` (`secretID`),
  KEY `key_type` (`credentialType`),
  KEY `key_provides` (`providesType`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `passphrase_credentialtransaction` (
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

CREATE TABLE `passphrase_secret` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `secretData` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phragment` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phragment`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phragment_fragment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `path` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `depth` int(10) unsigned NOT NULL,
  `latestVersionPHID` varbinary(64) DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phragment_fragmentversion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `fragmentPHID` varbinary(64) NOT NULL,
  `filePHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_version` (`fragmentPHID`,`sequence`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phragment_snapshot` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `primaryFragmentPHID` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`primaryFragmentPHID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phragment_snapshotchild` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `snapshotPHID` varbinary(64) NOT NULL,
  `fragmentPHID` varbinary(64) NOT NULL,
  `fragmentVersionPHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_child` (`snapshotPHID`,`fragmentPHID`,`fragmentVersionPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_dashboard` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_dashboard`;

CREATE TABLE `dashboard` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `layoutConfig` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `dashboard_install` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `installerPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `applicationClass` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dashboardPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`applicationClass`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `dashboard_panel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `panelType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `isArchived` tinyint(1) NOT NULL DEFAULT '0',
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `dashboard_paneltransaction` (
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

CREATE TABLE `dashboard_transaction` (
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

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_system` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_system`;

CREATE TABLE `system_actionlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actorHash` binary(12) NOT NULL,
  `actorIdentity` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `action` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `score` double NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_epoch` (`epoch`),
  KEY `key_action` (`actorHash`,`action`,`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `system_destructionlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectClass` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `rootLogID` int(10) unsigned DEFAULT NULL,
  `objectPHID` varbinary(64) DEFAULT NULL,
  `objectMonogram` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_epoch` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_fund` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_fund`;

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `fund_backer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `initiativePHID` varbinary(64) NOT NULL,
  `backerPHID` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `amountAsCurrency` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_initiative` (`initiativePHID`),
  KEY `key_backer` (`backerPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `fund_backertransaction` (
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

CREATE TABLE `fund_initiative` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `ownerPHID` varbinary(64) NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `merchantPHID` varbinary(64) DEFAULT NULL,
  `risks` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `totalAsCurrency` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_status` (`status`),
  KEY `key_owner` (`ownerPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `fund_initiativetransaction` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_almanac` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_almanac`;

CREATE TABLE `almanac_binding` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `servicePHID` varbinary(64) NOT NULL,
  `devicePHID` varbinary(64) NOT NULL,
  `interfacePHID` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_service` (`servicePHID`,`interfacePHID`),
  KEY `key_device` (`devicePHID`),
  KEY `key_interface` (`interfacePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_bindingtransaction` (
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

CREATE TABLE `almanac_device` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `nameIndex` binary(12) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `isLocked` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`nameIndex`),
  KEY `key_nametext` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_devicetransaction` (
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

CREATE TABLE `almanac_interface` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `devicePHID` varbinary(64) NOT NULL,
  `networkPHID` varbinary(64) NOT NULL,
  `address` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `port` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_location` (`networkPHID`,`address`,`port`),
  KEY `key_device` (`devicePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_network` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_networktransaction` (
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

CREATE TABLE `almanac_property` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldName` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `fieldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_service` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameIndex` binary(12) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `serviceClass` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isLocked` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`nameIndex`),
  KEY `key_nametext` (`name`),
  KEY `key_class` (`serviceClass`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_servicetransaction` (
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

CREATE TABLE `edge` (
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

CREATE TABLE `edgedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};
