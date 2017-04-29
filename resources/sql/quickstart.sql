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
  `hostPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isCancelled` tinyint(1) NOT NULL,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `isAllDay` tinyint(1) NOT NULL,
  `icon` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isRecurring` tinyint(1) NOT NULL,
  `instanceOfEventPHID` varbinary(64) DEFAULT NULL,
  `sequenceIndex` int(10) unsigned DEFAULT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  `isStub` tinyint(1) NOT NULL,
  `utcInitialEpoch` int(10) unsigned NOT NULL,
  `utcUntilEpoch` int(10) unsigned DEFAULT NULL,
  `utcInstanceEpoch` int(10) unsigned DEFAULT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `importAuthorPHID` varbinary(64) DEFAULT NULL,
  `importSourcePHID` varbinary(64) DEFAULT NULL,
  `importUIDIndex` binary(12) DEFAULT NULL,
  `importUID` longtext COLLATE {$COLLATE_TEXT},
  `seriesParentPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_instance` (`instanceOfEventPHID`,`sequenceIndex`),
  UNIQUE KEY `key_rdate` (`instanceOfEventPHID`,`utcInstanceEpoch`),
  KEY `key_epoch` (`utcInitialEpoch`,`utcUntilEpoch`),
  KEY `key_series` (`seriesParentPHID`,`utcInitialEpoch`),
  KEY `key_space` (`spacePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_eventinvitee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventPHID` varbinary(64) NOT NULL,
  `inviteePHID` varbinary(64) NOT NULL,
  `inviterPHID` varbinary(64) NOT NULL,
  `status` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `availability` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_event` (`eventPHID`,`inviteePHID`),
  KEY `key_invitee` (`inviteePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_eventtransaction` (
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

CREATE TABLE `calendar_eventtransaction_comment` (
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

CREATE TABLE `calendar_export` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `policyMode` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `queryKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `secretKey` binary(20) NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_secret` (`secretKey`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_author` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_exporttransaction` (
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

CREATE TABLE `calendar_externalinvitee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameIndex` binary(12) NOT NULL,
  `uri` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `sourcePHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`nameIndex`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_import` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `engineType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `triggerPHID` varbinary(64) DEFAULT NULL,
  `triggerFrequency` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_author` (`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_importlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `importPHID` varbinary(64) NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_import` (`importPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `calendar_importtransaction` (
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

CREATE TABLE `calendar_notification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventPHID` varbinary(64) NOT NULL,
  `utcInitialEpoch` int(10) unsigned NOT NULL,
  `targetPHID` varbinary(64) NOT NULL,
  `didNotifyEpoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_notify` (`eventPHID`,`utcInitialEpoch`,`targetPHID`)
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
  `spacePHID` varbinary(64) DEFAULT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_epoch` (`epoch`),
  KEY `key_author` (`authorPHID`,`epoch`),
  KEY `key_space` (`spacePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `countdown_transaction` (
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

CREATE TABLE `countdown_transaction_comment` (
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
  `status` varchar(8) COLLATE {$COLLATE_TEXT} NOT NULL,
  `runningAsUser` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
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
  `oldFile` longblob,
  `filename` longblob NOT NULL,
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
  `creationMethod` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `repositoryUUID` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `commitPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `revisionID` (`revisionID`),
  KEY `key_commit` (`commitPHID`)
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

CREATE TABLE `differential_hiddencomment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `commentID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_user` (`userPHID`,`commentID`),
  KEY `key_comment` (`commentID`)
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

CREATE TABLE `differential_reviewer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionPHID` varbinary(64) NOT NULL,
  `reviewerPHID` varbinary(64) NOT NULL,
  `reviewerStatus` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `lastActionDiffPHID` varbinary(64) DEFAULT NULL,
  `lastCommentDiffPHID` varbinary(64) DEFAULT NULL,
  `lastActorPHID` varbinary(64) DEFAULT NULL,
  `voidedPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_revision` (`revisionPHID`,`reviewerPHID`),
  KEY `key_reviewer` (`reviewerPHID`,`revisionPHID`)
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
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) DEFAULT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`,`status`),
  KEY `repositoryPHID` (`repositoryPHID`),
  KEY `key_status` (`status`,`phid`)
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

CREATE TABLE `draft_versioneddraft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_object` (`objectPHID`,`authorPHID`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_drydock` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_drydock`;

CREATE TABLE `drydock_authorization` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `blueprintPHID` varbinary(64) NOT NULL,
  `blueprintAuthorizationState` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `objectAuthorizationState` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_unique` (`objectPHID`,`blueprintPHID`),
  KEY `key_blueprint` (`blueprintPHID`,`blueprintAuthorizationState`),
  KEY `key_object` (`objectPHID`,`objectAuthorizationState`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_blueprint` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `className` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `blueprintName` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `details` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_blueprintname_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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

CREATE TABLE `drydock_command` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) NOT NULL,
  `targetPHID` varbinary(64) NOT NULL,
  `command` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isConsumed` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_target` (`targetPHID`,`isConsumed`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_lease` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `until` int(10) unsigned DEFAULT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `attributes` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `resourceType` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `resourcePHID` varbinary(64) DEFAULT NULL,
  `authorizingPHID` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_resource` (`resourcePHID`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `epoch` int(10) unsigned NOT NULL,
  `blueprintPHID` varbinary(64) DEFAULT NULL,
  `resourcePHID` varbinary(64) DEFAULT NULL,
  `leasePHID` varbinary(64) DEFAULT NULL,
  `type` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `epoch` (`epoch`),
  KEY `key_blueprint` (`blueprintPHID`,`type`),
  KEY `key_resource` (`resourcePHID`,`type`),
  KEY `key_lease` (`leasePHID`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_repositoryoperation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `repositoryTarget` longblob NOT NULL,
  `operationType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `operationState` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isDismissed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_object` (`objectPHID`),
  KEY `key_repository` (`repositoryPHID`,`operationState`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_resource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `type` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `attributes` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `capabilities` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `blueprintPHID` varbinary(64) NOT NULL,
  `until` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_type` (`type`,`status`),
  KEY `key_blueprint` (`blueprintPHID`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `drydock_slotlock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ownerPHID` varbinary(64) NOT NULL,
  `lockIndex` binary(12) NOT NULL,
  `lockKey` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_lock` (`lockIndex`),
  KEY `key_owner` (`ownerPHID`)
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
  KEY `userPHID_2` (`userPHID`,`hasViewed`,`primaryObjectPHID`),
  KEY `key_object` (`primaryObjectPHID`),
  KEY `key_chronological` (`chronologicalKey`)
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
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} DEFAULT NULL,
  `mimeType` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `byteSize` bigint(20) unsigned NOT NULL,
  `storageEngine` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `storageFormat` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `storageHandle` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `secretKey` binary(20) DEFAULT NULL,
  `contentHash` binary(64) DEFAULT NULL,
  `metadata` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `ttl` int(10) unsigned DEFAULT NULL,
  `isExplicitUpload` tinyint(1) DEFAULT '1',
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `isPartial` tinyint(1) NOT NULL DEFAULT '0',
  `builtinKey` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `isDeleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_builtin` (`builtinKey`),
  KEY `authorPHID` (`authorPHID`),
  KEY `contentHash` (`contentHash`),
  KEY `key_ttl` (`ttl`),
  KEY `key_dateCreated` (`dateCreated`),
  KEY `key_partial` (`authorPHID`,`isPartial`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_chunk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chunkHandle` binary(12) NOT NULL,
  `byteStart` bigint(20) unsigned NOT NULL,
  `byteEnd` bigint(20) unsigned NOT NULL,
  `dataFilePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `key_file` (`chunkHandle`,`byteStart`,`byteEnd`),
  KEY `key_data` (`dataFilePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_externalrequest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filePHID` varbinary(64) DEFAULT NULL,
  `ttl` int(10) unsigned NOT NULL,
  `uri` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `uriIndex` binary(12) NOT NULL,
  `isSuccessful` tinyint(1) NOT NULL,
  `responseMessage` longtext COLLATE {$COLLATE_TEXT},
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_uriindex` (`uriIndex`),
  KEY `key_ttl` (`ttl`),
  KEY `key_file` (`filePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `file_filename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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
  UNIQUE KEY `key_version` (`transactionPHID`,`commentVersion`)
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
  `planAutoKey` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `buildParameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `initiatorPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_planautokey` (`buildablePHID`,`planAutoKey`),
  KEY `key_buildable` (`buildablePHID`),
  KEY `key_plan` (`buildPlanPHID`),
  KEY `key_status` (`buildStatus`),
  KEY `key_initiator` (`initiatorPHID`)
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
  `phid` varbinary(64) NOT NULL,
  `artifactType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `artifactIndex` binary(12) NOT NULL,
  `artifactKey` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `artifactData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `buildTargetPHID` varbinary(64) NOT NULL,
  `isReleased` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_artifact` (`artifactType`,`artifactIndex`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_garbagecollect` (`artifactType`,`dateCreated`),
  KEY `key_target` (`buildTargetPHID`,`artifactType`)
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

CREATE TABLE `harbormaster_buildlintmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `buildTargetPHID` varbinary(64) NOT NULL,
  `path` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `line` int(10) unsigned DEFAULT NULL,
  `characterOffset` int(10) unsigned DEFAULT NULL,
  `code` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `severity` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_target` (`buildTargetPHID`)
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
  `name` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `planStatus` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `planAutoKey` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_planautokey` (`planAutoKey`),
  KEY `key_status` (`planStatus`),
  KEY `key_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `harbormaster_buildplanname_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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
  `stepAutoKey` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_stepautokey` (`buildPlanPHID`,`stepAutoKey`),
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

CREATE TABLE `harbormaster_buildunitmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `buildTargetPHID` varbinary(64) NOT NULL,
  `engine` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `namespace` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `result` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `duration` double DEFAULT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_target` (`buildTargetPHID`)
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
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
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
  KEY `key_name` (`name`(128)),
  KEY `key_author` (`authorPHID`),
  KEY `key_ruletype` (`ruleType`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `herald_ruleapplied` (
  `ruleID` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  PRIMARY KEY (`ruleID`,`phid`),
  KEY `phid` (`phid`)
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
  `status` varchar(12) COLLATE {$COLLATE_TEXT} NOT NULL,
  `priority` int(10) unsigned NOT NULL,
  `title` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `originalTitle` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `ownerOrdering` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `originalEmailSource` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `subpriority` double NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `points` double DEFAULT NULL,
  `bridgedObjectPHID` varbinary(64) DEFAULT NULL,
  `subtype` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_bridgedobject` (`bridgedObjectPHID`),
  KEY `priority` (`priority`,`status`),
  KEY `status` (`status`),
  KEY `ownerPHID` (`ownerPHID`,`status`),
  KEY `authorPHID` (`authorPHID`,`status`),
  KEY `ownerOrdering` (`ownerOrdering`),
  KEY `priority_2` (`priority`,`subpriority`),
  KEY `key_dateCreated` (`dateCreated`),
  KEY `key_dateModified` (`dateModified`),
  KEY `key_title` (`title`(64)),
  KEY `key_subtype` (`subtype`),
  KEY `key_space` (`spacePHID`)
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

CREATE TABLE `hoststate` (
  `stateKey` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `stateValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`stateKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `patch_status` (
  `patch` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `applied` int(10) unsigned NOT NULL,
  `duration` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`patch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

INSERT INTO `patch_status` VALUES ('phabricator:000.project.sql',1492953699,NULL),('phabricator:0000.legacy.sql',1492953699,NULL),('phabricator:001.maniphest_projects.sql',1492953699,NULL),('phabricator:002.oauth.sql',1492953699,NULL),('phabricator:003.more_oauth.sql',1492953699,NULL),('phabricator:004.daemonrepos.sql',1492953699,NULL),('phabricator:005.workers.sql',1492953699,NULL),('phabricator:006.repository.sql',1492953699,NULL),('phabricator:007.daemonlog.sql',1492953699,NULL),('phabricator:008.repoopt.sql',1492953699,NULL),('phabricator:009.repo_summary.sql',1492953699,NULL),('phabricator:010.herald.sql',1492953699,NULL),('phabricator:011.badcommit.sql',1492953699,NULL),('phabricator:012.dropphidtype.sql',1492953699,NULL),('phabricator:013.commitdetail.sql',1492953699,NULL),('phabricator:014.shortcuts.sql',1492953699,NULL),('phabricator:015.preferences.sql',1492953699,NULL),('phabricator:016.userrealnameindex.sql',1492953699,NULL),('phabricator:017.sessionkeys.sql',1492953700,NULL),('phabricator:018.owners.sql',1492953700,NULL),('phabricator:019.arcprojects.sql',1492953700,NULL),('phabricator:020.pathcapital.sql',1492953700,NULL),('phabricator:021.xhpastview.sql',1492953700,NULL),('phabricator:022.differentialcommit.sql',1492953700,NULL),('phabricator:023.dxkeys.sql',1492953700,NULL),('phabricator:024.mlistkeys.sql',1492953700,NULL),('phabricator:025.commentopt.sql',1492953700,NULL),('phabricator:026.diffpropkey.sql',1492953700,NULL),('phabricator:027.metamtakeys.sql',1492953700,NULL),('phabricator:028.systemagent.sql',1492953700,NULL),('phabricator:029.cursors.sql',1492953700,NULL),('phabricator:030.imagemacro.sql',1492953700,NULL),('phabricator:031.workerrace.sql',1492953700,NULL),('phabricator:032.viewtime.sql',1492953700,NULL),('phabricator:033.privtest.sql',1492953700,NULL),('phabricator:034.savedheader.sql',1492953700,NULL),('phabricator:035.proxyimage.sql',1492953700,NULL),('phabricator:036.mailkey.sql',1492953700,NULL),('phabricator:037.setuptest.sql',1492953700,NULL),('phabricator:038.admin.sql',1492953700,NULL),('phabricator:039.userlog.sql',1492953700,NULL),('phabricator:040.transform.sql',1492953700,NULL),('phabricator:041.heraldrepetition.sql',1492953700,NULL),('phabricator:042.commentmetadata.sql',1492953700,NULL),('phabricator:043.pastebin.sql',1492953700,NULL),('phabricator:044.countdown.sql',1492953700,NULL),('phabricator:045.timezone.sql',1492953700,NULL),('phabricator:046.conduittoken.sql',1492953700,NULL),('phabricator:047.projectstatus.sql',1492953700,NULL),('phabricator:048.relationshipkeys.sql',1492953700,NULL),('phabricator:049.projectowner.sql',1492953700,NULL),('phabricator:050.taskdenormal.sql',1492953700,NULL),('phabricator:051.projectfilter.sql',1492953700,NULL),('phabricator:052.pastelanguage.sql',1492953700,NULL),('phabricator:053.feed.sql',1492953700,NULL),('phabricator:054.subscribers.sql',1492953700,NULL),('phabricator:055.add_author_to_files.sql',1492953700,NULL),('phabricator:056.slowvote.sql',1492953700,NULL),('phabricator:057.parsecache.sql',1492953701,NULL),('phabricator:058.missingkeys.sql',1492953701,NULL),('phabricator:059.engines.php',1492953701,NULL),('phabricator:060.phriction.sql',1492953701,NULL),('phabricator:061.phrictioncontent.sql',1492953701,NULL),('phabricator:062.phrictionmenu.sql',1492953701,NULL),('phabricator:063.pasteforks.sql',1492953701,NULL),('phabricator:064.subprojects.sql',1492953701,NULL),('phabricator:065.sshkeys.sql',1492953701,NULL),('phabricator:066.phrictioncontent.sql',1492953701,NULL),('phabricator:067.preferences.sql',1492953701,NULL),('phabricator:068.maniphestauxiliarystorage.sql',1492953701,NULL),('phabricator:069.heraldxscript.sql',1492953701,NULL),('phabricator:070.differentialaux.sql',1492953701,NULL),('phabricator:071.contentsource.sql',1492953701,NULL),('phabricator:072.blamerevert.sql',1492953701,NULL),('phabricator:073.reposymbols.sql',1492953701,NULL),('phabricator:074.affectedpath.sql',1492953701,NULL),('phabricator:075.revisionhash.sql',1492953701,NULL),('phabricator:076.indexedlanguages.sql',1492953701,NULL),('phabricator:077.originalemail.sql',1492953701,NULL),('phabricator:078.nametoken.sql',1492953701,NULL),('phabricator:079.nametokenindex.php',1492953701,NULL),('phabricator:080.filekeys.sql',1492953701,NULL),('phabricator:081.filekeys.php',1492953701,NULL),('phabricator:082.xactionkey.sql',1492953701,NULL),('phabricator:083.dxviewtime.sql',1492953701,NULL),('phabricator:084.pasteauthorkey.sql',1492953701,NULL),('phabricator:085.packagecommitrelationship.sql',1492953701,NULL),('phabricator:086.formeraffil.sql',1492953701,NULL),('phabricator:087.phrictiondelete.sql',1492953701,NULL),('phabricator:088.audit.sql',1492953701,NULL),('phabricator:089.projectwiki.sql',1492953701,NULL),('phabricator:090.forceuniqueprojectnames.php',1492953701,NULL),('phabricator:091.uniqueslugkey.sql',1492953701,NULL),('phabricator:092.dropgithubnotification.sql',1492953701,NULL),('phabricator:093.gitremotes.php',1492953701,NULL),('phabricator:094.phrictioncolumn.sql',1492953701,NULL),('phabricator:095.directory.sql',1492953701,NULL),('phabricator:096.filename.sql',1492953701,NULL),('phabricator:097.heraldruletypes.sql',1492953701,NULL),('phabricator:098.heraldruletypemigration.php',1492953701,NULL),('phabricator:099.drydock.sql',1492953701,NULL),('phabricator:100.projectxaction.sql',1492953701,NULL),('phabricator:101.heraldruleapplied.sql',1492953701,NULL),('phabricator:102.heraldcleanup.php',1492953701,NULL),('phabricator:103.heraldedithistory.sql',1492953701,NULL),('phabricator:104.searchkey.sql',1492953702,NULL),('phabricator:105.mimetype.sql',1492953702,NULL),('phabricator:106.chatlog.sql',1492953702,NULL),('phabricator:107.oauthserver.sql',1492953702,NULL),('phabricator:108.oauthscope.sql',1492953702,NULL),('phabricator:109.oauthclientphidkey.sql',1492953702,NULL),('phabricator:110.commitaudit.sql',1492953702,NULL),('phabricator:111.commitauditmigration.php',1492953702,NULL),('phabricator:112.oauthaccesscoderedirecturi.sql',1492953702,NULL),('phabricator:113.lastreviewer.sql',1492953702,NULL),('phabricator:114.auditrequest.sql',1492953702,NULL),('phabricator:115.prepareutf8.sql',1492953702,NULL),('phabricator:116.utf8-backup-first-expect-wait.sql',1492953704,NULL),('phabricator:117.repositorydescription.php',1492953704,NULL),('phabricator:118.auditinline.sql',1492953704,NULL),('phabricator:119.filehash.sql',1492953704,NULL),('phabricator:120.noop.sql',1492953704,NULL),('phabricator:121.drydocklog.sql',1492953704,NULL),('phabricator:122.flag.sql',1492953704,NULL),('phabricator:123.heraldrulelog.sql',1492953704,NULL),('phabricator:124.subpriority.sql',1492953704,NULL),('phabricator:125.ipv6.sql',1492953704,NULL),('phabricator:126.edges.sql',1492953704,NULL),('phabricator:127.userkeybody.sql',1492953704,NULL),('phabricator:128.phabricatorcom.sql',1492953704,NULL),('phabricator:129.savedquery.sql',1492953704,NULL),('phabricator:130.denormalrevisionquery.sql',1492953704,NULL),('phabricator:131.migraterevisionquery.php',1492953704,NULL),('phabricator:132.phame.sql',1492953704,NULL),('phabricator:133.imagemacro.sql',1492953704,NULL),('phabricator:134.emptysearch.sql',1492953704,NULL),('phabricator:135.datecommitted.sql',1492953704,NULL),('phabricator:136.sex.sql',1492953704,NULL),('phabricator:137.auditmetadata.sql',1492953704,NULL),('phabricator:138.notification.sql',1492953704,NULL),('phabricator:20121209.pholioxactions.sql',1492953705,NULL),('phabricator:20121209.xmacroadd.sql',1492953705,NULL),('phabricator:20121209.xmacromigrate.php',1492953705,NULL),('phabricator:20121209.xmacromigratekey.sql',1492953705,NULL),('phabricator:20121220.generalcache.sql',1492953705,NULL),('phabricator:20121226.config.sql',1492953705,NULL),('phabricator:20130101.confxaction.sql',1492953705,NULL),('phabricator:20130102.metamtareceivedmailmessageidhash.sql',1492953705,NULL),('phabricator:20130103.filemetadata.sql',1492953705,NULL),('phabricator:20130111.conpherence.sql',1492953705,NULL),('phabricator:20130127.altheraldtranscript.sql',1492953705,NULL),('phabricator:20130131.conpherencepics.sql',1492953705,NULL),('phabricator:20130201.revisionunsubscribed.php',1492953705,NULL),('phabricator:20130201.revisionunsubscribed.sql',1492953705,NULL),('phabricator:20130214.chatlogchannel.sql',1492953705,NULL),('phabricator:20130214.chatlogchannelid.sql',1492953705,NULL),('phabricator:20130214.token.sql',1492953705,NULL),('phabricator:20130215.phabricatorfileaddttl.sql',1492953705,NULL),('phabricator:20130217.cachettl.sql',1492953705,NULL),('phabricator:20130218.longdaemon.sql',1492953705,NULL),('phabricator:20130218.updatechannelid.php',1492953705,NULL),('phabricator:20130219.commitsummary.sql',1492953705,NULL),('phabricator:20130219.commitsummarymig.php',1492953705,NULL),('phabricator:20130222.dropchannel.sql',1492953705,NULL),('phabricator:20130226.commitkey.sql',1492953705,NULL),('phabricator:20130304.lintauthor.sql',1492953705,NULL),('phabricator:20130310.xactionmeta.sql',1492953705,NULL),('phabricator:20130317.phrictionedge.sql',1492953705,NULL),('phabricator:20130319.conpherence.sql',1492953705,NULL),('phabricator:20130319.phabricatorfileexplicitupload.sql',1492953705,NULL),('phabricator:20130320.phlux.sql',1492953705,NULL),('phabricator:20130321.token.sql',1492953705,NULL),('phabricator:20130322.phortune.sql',1492953705,NULL),('phabricator:20130323.phortunepayment.sql',1492953705,NULL),('phabricator:20130324.phortuneproduct.sql',1492953705,NULL),('phabricator:20130330.phrequent.sql',1492953705,NULL),('phabricator:20130403.conpherencecache.sql',1492953705,NULL),('phabricator:20130403.conpherencecachemig.php',1492953705,NULL),('phabricator:20130409.commitdrev.php',1492953705,NULL),('phabricator:20130417.externalaccount.sql',1492953705,NULL),('phabricator:20130423.conpherenceindices.sql',1492953706,NULL),('phabricator:20130423.phortunepaymentrevised.sql',1492953706,NULL),('phabricator:20130423.updateexternalaccount.sql',1492953705,NULL),('phabricator:20130426.search_savedquery.sql',1492953706,NULL),('phabricator:20130502.countdownrevamp1.sql',1492953706,NULL),('phabricator:20130502.countdownrevamp2.php',1492953706,NULL),('phabricator:20130502.countdownrevamp3.sql',1492953706,NULL),('phabricator:20130507.releephrqmailkey.sql',1492953706,NULL),('phabricator:20130507.releephrqmailkeypop.php',1492953706,NULL),('phabricator:20130507.releephrqsimplifycols.sql',1492953706,NULL),('phabricator:20130508.releephtransactions.sql',1492953706,NULL),('phabricator:20130508.releephtransactionsmig.php',1492953706,NULL),('phabricator:20130508.search_namedquery.sql',1492953706,NULL),('phabricator:20130513.receviedmailstatus.sql',1492953706,NULL),('phabricator:20130519.diviner.sql',1492953706,NULL),('phabricator:20130521.dropconphimages.sql',1492953706,NULL),('phabricator:20130523.maniphest_owners.sql',1492953706,NULL),('phabricator:20130524.repoxactions.sql',1492953706,NULL),('phabricator:20130529.macroauthor.sql',1492953706,NULL),('phabricator:20130529.macroauthormig.php',1492953706,NULL),('phabricator:20130530.macrodatekey.sql',1492953706,NULL),('phabricator:20130530.pastekeys.sql',1492953706,NULL),('phabricator:20130530.sessionhash.php',1492953706,NULL),('phabricator:20130531.filekeys.sql',1492953706,NULL),('phabricator:20130602.morediviner.sql',1492953706,NULL),('phabricator:20130602.namedqueries.sql',1492953706,NULL),('phabricator:20130606.userxactions.sql',1492953706,NULL),('phabricator:20130607.xaccount.sql',1492953706,NULL),('phabricator:20130611.migrateoauth.php',1492953706,NULL),('phabricator:20130611.nukeldap.php',1492953706,NULL),('phabricator:20130613.authdb.sql',1492953706,NULL),('phabricator:20130619.authconf.php',1492953706,NULL),('phabricator:20130620.diffxactions.sql',1492953706,NULL),('phabricator:20130621.diffcommentphid.sql',1492953706,NULL),('phabricator:20130621.diffcommentphidmig.php',1492953706,NULL),('phabricator:20130621.diffcommentunphid.sql',1492953706,NULL),('phabricator:20130622.doorkeeper.sql',1492953706,NULL),('phabricator:20130628.legalpadv0.sql',1492953706,NULL),('phabricator:20130701.conduitlog.sql',1492953706,NULL),('phabricator:20130703.legalpaddocdenorm.php',1492953707,NULL),('phabricator:20130703.legalpaddocdenorm.sql',1492953707,NULL),('phabricator:20130709.droptimeline.sql',1492953707,NULL),('phabricator:20130709.legalpadsignature.sql',1492953707,NULL),('phabricator:20130711.pholioimageobsolete.php',1492953707,NULL),('phabricator:20130711.pholioimageobsolete.sql',1492953707,NULL),('phabricator:20130711.pholioimageobsolete2.sql',1492953707,NULL),('phabricator:20130711.trimrealnames.php',1492953707,NULL),('phabricator:20130714.votexactions.sql',1492953707,NULL),('phabricator:20130715.votecomments.php',1492953707,NULL),('phabricator:20130715.voteedges.sql',1492953707,NULL),('phabricator:20130716.archivememberlessprojects.php',1492953707,NULL),('phabricator:20130722.pholioreplace.sql',1492953707,NULL),('phabricator:20130723.taskstarttime.sql',1492953707,NULL),('phabricator:20130726.ponderxactions.sql',1492953707,NULL),('phabricator:20130727.ponderquestionstatus.sql',1492953707,NULL),('phabricator:20130728.ponderunique.php',1492953707,NULL),('phabricator:20130728.ponderuniquekey.sql',1492953707,NULL),('phabricator:20130728.ponderxcomment.php',1492953707,NULL),('phabricator:20130731.releephcutpointidentifier.sql',1492953707,NULL),('phabricator:20130731.releephproject.sql',1492953707,NULL),('phabricator:20130731.releephrepoid.sql',1492953707,NULL),('phabricator:20130801.pastexactions.php',1492953707,NULL),('phabricator:20130801.pastexactions.sql',1492953707,NULL),('phabricator:20130802.heraldphid.sql',1492953707,NULL),('phabricator:20130802.heraldphids.php',1492953707,NULL),('phabricator:20130802.heraldphidukey.sql',1492953707,NULL),('phabricator:20130802.heraldxactions.sql',1492953707,NULL),('phabricator:20130805.pasteedges.sql',1492953707,NULL),('phabricator:20130805.pastemailkey.sql',1492953707,NULL),('phabricator:20130805.pastemailkeypop.php',1492953707,NULL),('phabricator:20130814.usercustom.sql',1492953707,NULL),('phabricator:20130820.file-mailkey-populate.php',1492953707,NULL),('phabricator:20130820.filemailkey.sql',1492953707,NULL),('phabricator:20130820.filexactions.sql',1492953707,NULL),('phabricator:20130820.releephxactions.sql',1492953707,NULL),('phabricator:20130826.divinernode.sql',1492953707,NULL),('phabricator:20130912.maniphest.1.touch.sql',1492953707,NULL),('phabricator:20130912.maniphest.2.created.sql',1492953707,NULL),('phabricator:20130912.maniphest.3.nameindex.sql',1492953707,NULL),('phabricator:20130912.maniphest.4.fillindex.php',1492953707,NULL),('phabricator:20130913.maniphest.1.migratesearch.php',1492953707,NULL),('phabricator:20130914.usercustom.sql',1492953707,NULL),('phabricator:20130915.maniphestcustom.sql',1492953707,NULL),('phabricator:20130915.maniphestmigrate.php',1492953707,NULL),('phabricator:20130915.maniphestqdrop.sql',1492953708,NULL),('phabricator:20130919.mfieldconf.php',1492953707,NULL),('phabricator:20130920.repokeyspolicy.sql',1492953707,NULL),('phabricator:20130921.mtransactions.sql',1492953707,NULL),('phabricator:20130921.xmigratemaniphest.php',1492953707,NULL),('phabricator:20130923.mrename.sql',1492953707,NULL),('phabricator:20130924.mdraftkey.sql',1492953707,NULL),('phabricator:20130925.mpolicy.sql',1492953707,NULL),('phabricator:20130925.xpolicy.sql',1492953707,NULL),('phabricator:20130926.dcustom.sql',1492953707,NULL),('phabricator:20130926.dinkeys.sql',1492953707,NULL),('phabricator:20130926.dinline.php',1492953708,NULL),('phabricator:20130927.audiomacro.sql',1492953708,NULL),('phabricator:20130929.filepolicy.sql',1492953708,NULL),('phabricator:20131004.dxedgekey.sql',1492953708,NULL),('phabricator:20131004.dxreviewers.php',1492953708,NULL),('phabricator:20131006.hdisable.sql',1492953708,NULL),('phabricator:20131010.pstorage.sql',1492953708,NULL),('phabricator:20131015.cpolicy.sql',1492953708,NULL),('phabricator:20131020.col1.sql',1492953708,NULL),('phabricator:20131020.harbormaster.sql',1492953708,NULL),('phabricator:20131020.pcustom.sql',1492953708,NULL),('phabricator:20131020.pxaction.sql',1492953708,NULL),('phabricator:20131020.pxactionmig.php',1492953708,NULL),('phabricator:20131025.repopush.sql',1492953708,NULL),('phabricator:20131026.commitstatus.sql',1492953708,NULL),('phabricator:20131030.repostatusmessage.sql',1492953708,NULL),('phabricator:20131031.vcspassword.sql',1492953708,NULL),('phabricator:20131105.buildstep.sql',1492953708,NULL),('phabricator:20131106.diffphid.1.col.sql',1492953708,NULL),('phabricator:20131106.diffphid.2.mig.php',1492953708,NULL),('phabricator:20131106.diffphid.3.key.sql',1492953708,NULL),('phabricator:20131106.nuance-v0.sql',1492953708,NULL),('phabricator:20131107.buildlog.sql',1492953708,NULL),('phabricator:20131112.userverified.1.col.sql',1492953708,NULL),('phabricator:20131112.userverified.2.mig.php',1492953708,NULL),('phabricator:20131118.ownerorder.php',1492953708,NULL),('phabricator:20131119.passphrase.sql',1492953708,NULL),('phabricator:20131120.nuancesourcetype.sql',1492953708,NULL),('phabricator:20131121.passphraseedge.sql',1492953708,NULL),('phabricator:20131121.repocredentials.1.col.sql',1492953708,NULL),('phabricator:20131121.repocredentials.2.mig.php',1492953708,NULL),('phabricator:20131122.repomirror.sql',1492953708,NULL),('phabricator:20131123.drydockblueprintpolicy.sql',1492953708,NULL),('phabricator:20131129.drydockresourceblueprint.sql',1492953708,NULL),('phabricator:20131204.pushlog.sql',1492953708,NULL),('phabricator:20131205.buildsteporder.sql',1492953708,NULL),('phabricator:20131205.buildstepordermig.php',1492953708,NULL),('phabricator:20131205.buildtargets.sql',1492953708,NULL),('phabricator:20131206.phragment.sql',1492953708,NULL),('phabricator:20131206.phragmentnull.sql',1492953708,NULL),('phabricator:20131208.phragmentsnapshot.sql',1492953708,NULL),('phabricator:20131211.phragmentedges.sql',1492953708,NULL),('phabricator:20131217.pushlogphid.1.col.sql',1492953708,NULL),('phabricator:20131217.pushlogphid.2.mig.php',1492953708,NULL),('phabricator:20131217.pushlogphid.3.key.sql',1492953708,NULL),('phabricator:20131219.pxdrop.sql',1492953708,NULL),('phabricator:20131224.harbormanual.sql',1492953708,NULL),('phabricator:20131227.heraldobject.sql',1492953708,NULL),('phabricator:20131231.dropshortcut.sql',1492953708,NULL),('phabricator:20131302.maniphestvalue.sql',1492953705,NULL),('phabricator:20140104.harbormastercmd.sql',1492953709,NULL),('phabricator:20140106.macromailkey.1.sql',1492953709,NULL),('phabricator:20140106.macromailkey.2.php',1492953709,NULL),('phabricator:20140108.ddbpname.1.sql',1492953709,NULL),('phabricator:20140108.ddbpname.2.php',1492953709,NULL),('phabricator:20140109.ddxactions.sql',1492953709,NULL),('phabricator:20140109.projectcolumnsdates.sql',1492953709,NULL),('phabricator:20140113.legalpadsig.1.sql',1492953709,NULL),('phabricator:20140113.legalpadsig.2.php',1492953709,NULL),('phabricator:20140115.auth.1.id.sql',1492953709,NULL),('phabricator:20140115.auth.2.expires.sql',1492953709,NULL),('phabricator:20140115.auth.3.unlimit.php',1492953709,NULL),('phabricator:20140115.legalpadsigkey.sql',1492953709,NULL),('phabricator:20140116.reporefcursor.sql',1492953709,NULL),('phabricator:20140126.diff.1.parentrevisionid.sql',1492953709,NULL),('phabricator:20140126.diff.2.repositoryphid.sql',1492953709,NULL),('phabricator:20140130.dash.1.board.sql',1492953709,NULL),('phabricator:20140130.dash.2.panel.sql',1492953709,NULL),('phabricator:20140130.dash.3.boardxaction.sql',1492953709,NULL),('phabricator:20140130.dash.4.panelxaction.sql',1492953709,NULL),('phabricator:20140130.mail.1.retry.sql',1492953709,NULL),('phabricator:20140130.mail.2.next.sql',1492953709,NULL),('phabricator:20140201.gc.1.mailsent.sql',1492953709,NULL),('phabricator:20140201.gc.2.mailreceived.sql',1492953709,NULL),('phabricator:20140205.cal.1.rename.sql',1492953709,NULL),('phabricator:20140205.cal.2.phid-col.sql',1492953709,NULL),('phabricator:20140205.cal.3.phid-mig.php',1492953709,NULL),('phabricator:20140205.cal.4.phid-key.sql',1492953709,NULL),('phabricator:20140210.herald.rule-condition-mig.php',1492953709,NULL),('phabricator:20140210.projcfield.1.blurb.php',1492953709,NULL),('phabricator:20140210.projcfield.2.piccol.sql',1492953709,NULL),('phabricator:20140210.projcfield.3.picmig.sql',1492953709,NULL),('phabricator:20140210.projcfield.4.memmig.sql',1492953709,NULL),('phabricator:20140210.projcfield.5.dropprofile.sql',1492953709,NULL),('phabricator:20140211.dx.1.nullablechangesetid.sql',1492953709,NULL),('phabricator:20140211.dx.2.migcommenttext.php',1492953709,NULL),('phabricator:20140211.dx.3.migsubscriptions.sql',1492953709,NULL),('phabricator:20140211.dx.999.drop.relationships.sql',1492953709,NULL),('phabricator:20140212.dx.1.armageddon.php',1492953709,NULL),('phabricator:20140214.clean.1.legacycommentid.sql',1492953709,NULL),('phabricator:20140214.clean.2.dropcomment.sql',1492953709,NULL),('phabricator:20140214.clean.3.dropinline.sql',1492953709,NULL),('phabricator:20140218.differentialdraft.sql',1492953709,NULL),('phabricator:20140218.passwords.1.extend.sql',1492953709,NULL),('phabricator:20140218.passwords.2.prefix.sql',1492953709,NULL),('phabricator:20140218.passwords.3.vcsextend.sql',1492953709,NULL),('phabricator:20140218.passwords.4.vcs.php',1492953709,NULL),('phabricator:20140223.bigutf8scratch.sql',1492953709,NULL),('phabricator:20140224.dxclean.1.datecommitted.sql',1492953709,NULL),('phabricator:20140226.dxcustom.1.fielddata.php',1492953709,NULL),('phabricator:20140226.dxcustom.99.drop.sql',1492953709,NULL),('phabricator:20140228.dxcomment.1.sql',1492953709,NULL),('phabricator:20140305.diviner.1.slugcol.sql',1492953709,NULL),('phabricator:20140305.diviner.2.slugkey.sql',1492953709,NULL),('phabricator:20140311.mdroplegacy.sql',1492953709,NULL),('phabricator:20140314.projectcolumn.1.statuscol.sql',1492953709,NULL),('phabricator:20140314.projectcolumn.2.statuskey.sql',1492953709,NULL),('phabricator:20140317.mupdatedkey.sql',1492953709,NULL),('phabricator:20140321.harbor.1.bxaction.sql',1492953709,NULL),('phabricator:20140321.mstatus.1.col.sql',1492953709,NULL),('phabricator:20140321.mstatus.2.mig.php',1492953709,NULL),('phabricator:20140323.harbor.1.renames.php',1492953709,NULL),('phabricator:20140323.harbor.2.message.sql',1492953709,NULL),('phabricator:20140325.push.1.event.sql',1492953709,NULL),('phabricator:20140325.push.2.eventphid.sql',1492953709,NULL),('phabricator:20140325.push.3.groups.php',1492953709,NULL),('phabricator:20140325.push.4.prune.sql',1492953709,NULL),('phabricator:20140326.project.1.colxaction.sql',1492953709,NULL),('phabricator:20140328.releeph.1.productxaction.sql',1492953709,NULL),('phabricator:20140330.flagtext.sql',1492953709,NULL),('phabricator:20140402.actionlog.sql',1492953709,NULL),('phabricator:20140410.accountsecret.1.sql',1492953709,NULL),('phabricator:20140410.accountsecret.2.php',1492953709,NULL),('phabricator:20140416.harbor.1.sql',1492953710,NULL),('phabricator:20140420.rel.1.objectphid.sql',1492953710,NULL),('phabricator:20140420.rel.2.objectmig.php',1492953710,NULL),('phabricator:20140421.slowvotecolumnsisclosed.sql',1492953710,NULL),('phabricator:20140423.session.1.hisec.sql',1492953710,NULL),('phabricator:20140427.mfactor.1.sql',1492953710,NULL),('phabricator:20140430.auth.1.partial.sql',1492953710,NULL),('phabricator:20140430.dash.1.paneltype.sql',1492953710,NULL),('phabricator:20140430.dash.2.edge.sql',1492953710,NULL),('phabricator:20140501.passphraselockcredential.sql',1492953710,NULL),('phabricator:20140501.remove.1.dlog.sql',1492953710,NULL),('phabricator:20140507.smstable.sql',1492953710,NULL),('phabricator:20140509.coverage.1.sql',1492953710,NULL),('phabricator:20140509.dashboardlayoutconfig.sql',1492953710,NULL),('phabricator:20140512.dparents.1.sql',1492953710,NULL),('phabricator:20140514.harbormasterbuildabletransaction.sql',1492953710,NULL),('phabricator:20140514.pholiomockclose.sql',1492953710,NULL),('phabricator:20140515.trust-emails.sql',1492953710,NULL),('phabricator:20140517.dxbinarycache.sql',1492953710,NULL),('phabricator:20140518.dxmorebinarycache.sql',1492953710,NULL),('phabricator:20140519.dashboardinstall.sql',1492953710,NULL),('phabricator:20140520.authtemptoken.sql',1492953710,NULL),('phabricator:20140521.projectslug.1.create.sql',1492953710,NULL),('phabricator:20140521.projectslug.2.mig.php',1492953710,NULL),('phabricator:20140522.projecticon.sql',1492953710,NULL),('phabricator:20140524.auth.mfa.cache.sql',1492953710,NULL),('phabricator:20140525.hunkmodern.sql',1492953710,NULL),('phabricator:20140615.pholioedit.1.sql',1492953710,NULL),('phabricator:20140615.pholioedit.2.sql',1492953710,NULL),('phabricator:20140617.daemon.explicit-argv.sql',1492953710,NULL),('phabricator:20140617.daemonlog.sql',1492953710,NULL),('phabricator:20140624.projcolor.1.sql',1492953710,NULL),('phabricator:20140624.projcolor.2.sql',1492953710,NULL),('phabricator:20140629.dasharchive.1.sql',1492953710,NULL),('phabricator:20140629.legalsig.1.sql',1492953710,NULL),('phabricator:20140629.legalsig.2.php',1492953710,NULL),('phabricator:20140701.legalexemption.1.sql',1492953710,NULL),('phabricator:20140701.legalexemption.2.sql',1492953710,NULL),('phabricator:20140703.legalcorp.1.sql',1492953710,NULL),('phabricator:20140703.legalcorp.2.sql',1492953710,NULL),('phabricator:20140703.legalcorp.3.sql',1492953710,NULL),('phabricator:20140703.legalcorp.4.sql',1492953710,NULL),('phabricator:20140703.legalcorp.5.sql',1492953710,NULL),('phabricator:20140704.harbormasterstep.1.sql',1492953710,NULL),('phabricator:20140704.harbormasterstep.2.sql',1492953710,NULL),('phabricator:20140704.legalpreamble.1.sql',1492953710,NULL),('phabricator:20140706.harbormasterdepend.1.php',1492953710,NULL),('phabricator:20140706.pedge.1.sql',1492953710,NULL),('phabricator:20140711.pnames.1.sql',1492953710,NULL),('phabricator:20140711.pnames.2.php',1492953710,NULL),('phabricator:20140711.workerpriority.sql',1492953710,NULL),('phabricator:20140712.projcoluniq.sql',1492953710,NULL),('phabricator:20140721.phortune.1.cart.sql',1492953710,NULL),('phabricator:20140721.phortune.2.purchase.sql',1492953710,NULL),('phabricator:20140721.phortune.3.charge.sql',1492953710,NULL),('phabricator:20140721.phortune.4.cartstatus.sql',1492953710,NULL),('phabricator:20140721.phortune.5.cstatusdefault.sql',1492953710,NULL),('phabricator:20140721.phortune.6.onetimecharge.sql',1492953710,NULL),('phabricator:20140721.phortune.7.nullmethod.sql',1492953710,NULL),('phabricator:20140722.appname.php',1492953710,NULL),('phabricator:20140722.audit.1.xactions.sql',1492953710,NULL),('phabricator:20140722.audit.2.comments.sql',1492953710,NULL),('phabricator:20140722.audit.3.miginlines.php',1492953710,NULL),('phabricator:20140722.audit.4.migtext.php',1492953710,NULL),('phabricator:20140722.renameauth.php',1492953710,NULL),('phabricator:20140723.apprenamexaction.sql',1492953710,NULL),('phabricator:20140725.audit.1.migxactions.php',1492953710,NULL),('phabricator:20140731.audit.1.subscribers.php',1492953710,NULL),('phabricator:20140731.cancdn.php',1492953710,NULL),('phabricator:20140731.harbormasterstepdesc.sql',1492953710,NULL),('phabricator:20140805.boardcol.1.sql',1492953710,NULL),('phabricator:20140805.boardcol.2.php',1492953710,NULL),('phabricator:20140807.harbormastertargettime.sql',1492953711,NULL),('phabricator:20140808.boardprop.1.sql',1492953711,NULL),('phabricator:20140808.boardprop.2.sql',1492953711,NULL),('phabricator:20140808.boardprop.3.php',1492953711,NULL),('phabricator:20140811.blob.1.sql',1492953711,NULL),('phabricator:20140811.blob.2.sql',1492953711,NULL),('phabricator:20140812.projkey.1.sql',1492953711,NULL),('phabricator:20140812.projkey.2.sql',1492953711,NULL),('phabricator:20140814.passphrasecredentialconduit.sql',1492953711,NULL),('phabricator:20140815.cancdncase.php',1492953711,NULL),('phabricator:20140818.harbormasterindex.1.sql',1492953711,NULL),('phabricator:20140821.harbormasterbuildgen.1.sql',1492953711,NULL),('phabricator:20140822.daemonenvhash.sql',1492953711,NULL),('phabricator:20140902.almanacdevice.1.sql',1492953711,NULL),('phabricator:20140904.macroattach.php',1492953711,NULL),('phabricator:20140911.fund.1.initiative.sql',1492953711,NULL),('phabricator:20140911.fund.2.xaction.sql',1492953711,NULL),('phabricator:20140911.fund.3.edge.sql',1492953711,NULL),('phabricator:20140911.fund.4.backer.sql',1492953711,NULL),('phabricator:20140911.fund.5.backxaction.sql',1492953711,NULL),('phabricator:20140914.betaproto.php',1492953711,NULL),('phabricator:20140917.project.canlock.sql',1492953711,NULL),('phabricator:20140918.schema.1.dropaudit.sql',1492953711,NULL),('phabricator:20140918.schema.2.dropauditinline.sql',1492953711,NULL),('phabricator:20140918.schema.3.wipecache.sql',1492953711,NULL),('phabricator:20140918.schema.4.cachetype.sql',1492953711,NULL),('phabricator:20140918.schema.5.slowvote.sql',1492953711,NULL),('phabricator:20140919.schema.01.calstatus.sql',1492953711,NULL),('phabricator:20140919.schema.02.calname.sql',1492953711,NULL),('phabricator:20140919.schema.03.dropaux.sql',1492953711,NULL),('phabricator:20140919.schema.04.droptaskproj.sql',1492953711,NULL),('phabricator:20140926.schema.01.droprelev.sql',1492953711,NULL),('phabricator:20140926.schema.02.droprelreqev.sql',1492953711,NULL),('phabricator:20140926.schema.03.dropldapinfo.sql',1492953711,NULL),('phabricator:20140926.schema.04.dropoauthinfo.sql',1492953711,NULL),('phabricator:20140926.schema.05.dropprojaffil.sql',1492953711,NULL),('phabricator:20140926.schema.06.dropsubproject.sql',1492953711,NULL),('phabricator:20140926.schema.07.droppondcom.sql',1492953711,NULL),('phabricator:20140927.schema.01.dropsearchq.sql',1492953711,NULL),('phabricator:20140927.schema.02.pholio1.sql',1492953711,NULL),('phabricator:20140927.schema.03.pholio2.sql',1492953711,NULL),('phabricator:20140927.schema.04.pholio3.sql',1492953711,NULL),('phabricator:20140927.schema.05.phragment1.sql',1492953711,NULL),('phabricator:20140927.schema.06.releeph1.sql',1492953711,NULL),('phabricator:20141001.schema.01.version.sql',1492953711,NULL),('phabricator:20141001.schema.02.taskmail.sql',1492953711,NULL),('phabricator:20141002.schema.01.liskcounter.sql',1492953711,NULL),('phabricator:20141002.schema.02.draftnull.sql',1492953711,NULL),('phabricator:20141004.currency.01.sql',1492953711,NULL),('phabricator:20141004.currency.02.sql',1492953711,NULL),('phabricator:20141004.currency.03.sql',1492953711,NULL),('phabricator:20141004.currency.04.sql',1492953711,NULL),('phabricator:20141004.currency.05.sql',1492953711,NULL),('phabricator:20141004.currency.06.sql',1492953711,NULL),('phabricator:20141004.harborliskcounter.sql',1492953711,NULL),('phabricator:20141005.phortuneproduct.sql',1492953711,NULL),('phabricator:20141006.phortunecart.sql',1492953711,NULL),('phabricator:20141006.phortunemerchant.sql',1492953711,NULL),('phabricator:20141006.phortunemerchantx.sql',1492953711,NULL),('phabricator:20141007.fundmerchant.sql',1492953711,NULL),('phabricator:20141007.fundrisks.sql',1492953711,NULL),('phabricator:20141007.fundtotal.sql',1492953711,NULL),('phabricator:20141007.phortunecartmerchant.sql',1492953711,NULL),('phabricator:20141007.phortunecharge.sql',1492953711,NULL),('phabricator:20141007.phortunepayment.sql',1492953712,NULL),('phabricator:20141007.phortuneprovider.sql',1492953712,NULL),('phabricator:20141007.phortuneproviderx.sql',1492953712,NULL),('phabricator:20141008.phortunemerchdesc.sql',1492953712,NULL),('phabricator:20141008.phortuneprovdis.sql',1492953712,NULL),('phabricator:20141008.phortunerefund.sql',1492953712,NULL),('phabricator:20141010.fundmailkey.sql',1492953712,NULL),('phabricator:20141011.phortunemerchedit.sql',1492953712,NULL),('phabricator:20141012.phortunecartxaction.sql',1492953712,NULL),('phabricator:20141013.phortunecartkey.sql',1492953712,NULL),('phabricator:20141016.almanac.device.sql',1492953712,NULL),('phabricator:20141016.almanac.dxaction.sql',1492953712,NULL),('phabricator:20141016.almanac.interface.sql',1492953712,NULL),('phabricator:20141016.almanac.network.sql',1492953712,NULL),('phabricator:20141016.almanac.nxaction.sql',1492953712,NULL),('phabricator:20141016.almanac.service.sql',1492953712,NULL),('phabricator:20141016.almanac.sxaction.sql',1492953712,NULL),('phabricator:20141017.almanac.binding.sql',1492953712,NULL),('phabricator:20141017.almanac.bxaction.sql',1492953712,NULL),('phabricator:20141025.phriction.1.xaction.sql',1492953712,NULL),('phabricator:20141025.phriction.2.xaction.sql',1492953712,NULL),('phabricator:20141025.phriction.mailkey.sql',1492953712,NULL),('phabricator:20141103.almanac.1.delprop.sql',1492953712,NULL),('phabricator:20141103.almanac.2.addprop.sql',1492953712,NULL),('phabricator:20141104.almanac.3.edge.sql',1492953712,NULL),('phabricator:20141105.ssh.1.rename.sql',1492953712,NULL),('phabricator:20141106.dropold.sql',1492953712,NULL),('phabricator:20141106.uniqdrafts.php',1492953712,NULL),('phabricator:20141107.phriction.policy.1.sql',1492953712,NULL),('phabricator:20141107.phriction.policy.2.php',1492953712,NULL),('phabricator:20141107.phriction.popkeys.php',1492953712,NULL),('phabricator:20141107.ssh.1.colname.sql',1492953712,NULL),('phabricator:20141107.ssh.2.keyhash.sql',1492953712,NULL),('phabricator:20141107.ssh.3.keyindex.sql',1492953712,NULL),('phabricator:20141107.ssh.4.keymig.php',1492953712,NULL),('phabricator:20141107.ssh.5.indexnull.sql',1492953712,NULL),('phabricator:20141107.ssh.6.indexkey.sql',1492953712,NULL),('phabricator:20141107.ssh.7.colnull.sql',1492953712,NULL),('phabricator:20141113.auditdupes.php',1492953712,NULL),('phabricator:20141118.diffxaction.sql',1492953712,NULL),('phabricator:20141119.commitpedge.sql',1492953712,NULL),('phabricator:20141119.differential.diff.policy.sql',1492953712,NULL),('phabricator:20141119.sshtrust.sql',1492953712,NULL),('phabricator:20141123.taskpriority.1.sql',1492953712,NULL),('phabricator:20141123.taskpriority.2.sql',1492953712,NULL),('phabricator:20141210.maniphestsubscribersmig.1.sql',1492953712,NULL),('phabricator:20141210.maniphestsubscribersmig.2.sql',1492953712,NULL),('phabricator:20141210.reposervice.sql',1492953712,NULL),('phabricator:20141212.conduittoken.sql',1492953712,NULL),('phabricator:20141215.almanacservicetype.sql',1492953712,NULL),('phabricator:20141217.almanacdevicelock.sql',1492953712,NULL),('phabricator:20141217.almanaclock.sql',1492953712,NULL),('phabricator:20141218.maniphestcctxn.php',1492953712,NULL),('phabricator:20141222.maniphestprojtxn.php',1492953712,NULL),('phabricator:20141223.daemonloguser.sql',1492953712,NULL),('phabricator:20141223.daemonobjectphid.sql',1492953712,NULL),('phabricator:20141230.pasteeditpolicycolumn.sql',1492953712,NULL),('phabricator:20141230.pasteeditpolicyexisting.sql',1492953712,NULL),('phabricator:20150102.policyname.php',1492953712,NULL),('phabricator:20150102.tasksubscriber.sql',1492953712,NULL),('phabricator:20150105.conpsearch.sql',1492953712,NULL),('phabricator:20150114.oauthserver.client.policy.sql',1492953713,NULL),('phabricator:20150115.applicationemails.sql',1492953713,NULL),('phabricator:20150115.trigger.1.sql',1492953713,NULL),('phabricator:20150115.trigger.2.sql',1492953713,NULL),('phabricator:20150116.maniphestapplicationemails.php',1492953713,NULL),('phabricator:20150120.maniphestdefaultauthor.php',1492953713,NULL),('phabricator:20150124.subs.1.sql',1492953713,NULL),('phabricator:20150129.pastefileapplicationemails.php',1492953713,NULL),('phabricator:20150130.phortune.1.subphid.sql',1492953713,NULL),('phabricator:20150130.phortune.2.subkey.sql',1492953713,NULL),('phabricator:20150131.phortune.1.defaultpayment.sql',1492953713,NULL),('phabricator:20150205.authprovider.autologin.sql',1492953713,NULL),('phabricator:20150205.daemonenv.sql',1492953713,NULL),('phabricator:20150209.invite.sql',1492953713,NULL),('phabricator:20150209.oauthclient.trust.sql',1492953713,NULL),('phabricator:20150210.invitephid.sql',1492953713,NULL),('phabricator:20150212.legalpad.session.1.sql',1492953713,NULL),('phabricator:20150212.legalpad.session.2.sql',1492953713,NULL),('phabricator:20150219.scratch.nonmutable.sql',1492953713,NULL),('phabricator:20150223.daemon.1.id.sql',1492953713,NULL),('phabricator:20150223.daemon.2.idlegacy.sql',1492953713,NULL),('phabricator:20150223.daemon.3.idkey.sql',1492953713,NULL),('phabricator:20150312.filechunk.1.sql',1492953713,NULL),('phabricator:20150312.filechunk.2.sql',1492953713,NULL),('phabricator:20150312.filechunk.3.sql',1492953713,NULL),('phabricator:20150317.conpherence.isroom.1.sql',1492953713,NULL),('phabricator:20150317.conpherence.isroom.2.sql',1492953713,NULL),('phabricator:20150317.conpherence.policy.sql',1492953713,NULL),('phabricator:20150410.nukeruleedit.sql',1492953713,NULL),('phabricator:20150420.invoice.1.sql',1492953713,NULL),('phabricator:20150420.invoice.2.sql',1492953713,NULL),('phabricator:20150425.isclosed.sql',1492953713,NULL),('phabricator:20150427.calendar.1.edge.sql',1492953713,NULL),('phabricator:20150427.calendar.1.xaction.sql',1492953713,NULL),('phabricator:20150427.calendar.2.xaction.sql',1492953713,NULL),('phabricator:20150428.calendar.1.iscancelled.sql',1492953713,NULL),('phabricator:20150428.calendar.1.name.sql',1492953713,NULL),('phabricator:20150429.calendar.1.invitee.sql',1492953713,NULL),('phabricator:20150430.calendar.1.policies.sql',1492953713,NULL),('phabricator:20150430.multimeter.1.sql',1492953713,NULL),('phabricator:20150430.multimeter.2.host.sql',1492953713,NULL),('phabricator:20150430.multimeter.3.viewer.sql',1492953713,NULL),('phabricator:20150430.multimeter.4.context.sql',1492953713,NULL),('phabricator:20150430.multimeter.5.label.sql',1492953713,NULL),('phabricator:20150501.calendar.1.reply.sql',1492953713,NULL),('phabricator:20150501.calendar.2.reply.php',1492953713,NULL),('phabricator:20150501.conpherencepics.sql',1492953713,NULL),('phabricator:20150503.repositorysymbols.1.sql',1492953713,NULL),('phabricator:20150503.repositorysymbols.2.php',1492953713,NULL),('phabricator:20150503.repositorysymbols.3.sql',1492953713,NULL),('phabricator:20150504.symbolsproject.1.php',1492953713,NULL),('phabricator:20150504.symbolsproject.2.sql',1492953713,NULL),('phabricator:20150506.calendarunnamedevents.1.php',1492953713,NULL),('phabricator:20150507.calendar.1.isallday.sql',1492953713,NULL),('phabricator:20150513.user.cache.1.sql',1492953713,NULL),('phabricator:20150514.calendar.status.sql',1492953713,NULL),('phabricator:20150514.phame.blog.xaction.sql',1492953713,NULL),('phabricator:20150514.user.cache.2.sql',1492953713,NULL),('phabricator:20150515.phame.post.xaction.sql',1492953713,NULL),('phabricator:20150515.project.mailkey.1.sql',1492953713,NULL),('phabricator:20150515.project.mailkey.2.php',1492953713,NULL),('phabricator:20150519.calendar.calendaricon.sql',1492953713,NULL),('phabricator:20150521.releephrepository.sql',1492953713,NULL),('phabricator:20150525.diff.hidden.1.sql',1492953713,NULL),('phabricator:20150526.owners.mailkey.1.sql',1492953713,NULL),('phabricator:20150526.owners.mailkey.2.php',1492953713,NULL),('phabricator:20150526.owners.xaction.sql',1492953713,NULL),('phabricator:20150527.calendar.recurringevents.sql',1492953713,NULL),('phabricator:20150601.spaces.1.namespace.sql',1492953713,NULL),('phabricator:20150601.spaces.2.xaction.sql',1492953714,NULL),('phabricator:20150602.mlist.1.sql',1492953714,NULL),('phabricator:20150602.mlist.2.php',1492953714,NULL),('phabricator:20150604.spaces.1.sql',1492953714,NULL),('phabricator:20150605.diviner.edges.sql',1492953714,NULL),('phabricator:20150605.diviner.editPolicy.sql',1492953714,NULL),('phabricator:20150605.diviner.xaction.sql',1492953714,NULL),('phabricator:20150606.mlist.1.php',1492953714,NULL),('phabricator:20150609.inline.sql',1492953714,NULL),('phabricator:20150609.spaces.1.pholio.sql',1492953714,NULL),('phabricator:20150609.spaces.2.maniphest.sql',1492953714,NULL),('phabricator:20150610.spaces.1.desc.sql',1492953714,NULL),('phabricator:20150610.spaces.2.edge.sql',1492953714,NULL),('phabricator:20150610.spaces.3.archive.sql',1492953714,NULL),('phabricator:20150611.spaces.1.mailxaction.sql',1492953714,NULL),('phabricator:20150611.spaces.2.appmail.sql',1492953714,NULL),('phabricator:20150616.divinerrepository.sql',1492953714,NULL),('phabricator:20150617.harbor.1.lint.sql',1492953714,NULL),('phabricator:20150617.harbor.2.unit.sql',1492953714,NULL),('phabricator:20150618.harbor.1.planauto.sql',1492953714,NULL),('phabricator:20150618.harbor.2.stepauto.sql',1492953714,NULL),('phabricator:20150618.harbor.3.buildauto.sql',1492953714,NULL),('phabricator:20150619.conpherencerooms.1.sql',1492953714,NULL),('phabricator:20150619.conpherencerooms.2.sql',1492953714,NULL),('phabricator:20150619.conpherencerooms.3.sql',1492953714,NULL),('phabricator:20150621.phrase.1.sql',1492953714,NULL),('phabricator:20150621.phrase.2.sql',1492953714,NULL),('phabricator:20150622.bulk.1.job.sql',1492953714,NULL),('phabricator:20150622.bulk.2.task.sql',1492953714,NULL),('phabricator:20150622.bulk.3.xaction.sql',1492953714,NULL),('phabricator:20150622.bulk.4.edge.sql',1492953714,NULL),('phabricator:20150622.metamta.1.phid-col.sql',1492953714,NULL),('phabricator:20150622.metamta.2.phid-mig.php',1492953714,NULL),('phabricator:20150622.metamta.3.phid-key.sql',1492953714,NULL),('phabricator:20150622.metamta.4.actor-phid-col.sql',1492953714,NULL),('phabricator:20150622.metamta.5.actor-phid-mig.php',1492953714,NULL),('phabricator:20150622.metamta.6.actor-phid-key.sql',1492953714,NULL),('phabricator:20150624.spaces.1.repo.sql',1492953714,NULL),('phabricator:20150626.spaces.1.calendar.sql',1492953714,NULL),('phabricator:20150630.herald.1.sql',1492953714,NULL),('phabricator:20150630.herald.2.sql',1492953714,NULL),('phabricator:20150701.herald.1.sql',1492953714,NULL),('phabricator:20150701.herald.2.sql',1492953714,NULL),('phabricator:20150702.spaces.1.slowvote.sql',1492953714,NULL),('phabricator:20150706.herald.1.sql',1492953714,NULL),('phabricator:20150707.herald.1.sql',1492953714,NULL),('phabricator:20150708.arcanistproject.sql',1492953714,NULL),('phabricator:20150708.herald.1.sql',1492953714,NULL),('phabricator:20150708.herald.2.sql',1492953714,NULL),('phabricator:20150708.herald.3.sql',1492953714,NULL),('phabricator:20150712.badges.1.sql',1492953714,NULL),('phabricator:20150714.spaces.countdown.1.sql',1492953714,NULL),('phabricator:20150717.herald.1.sql',1492953714,NULL),('phabricator:20150719.countdown.1.sql',1492953714,NULL),('phabricator:20150719.countdown.2.sql',1492953714,NULL),('phabricator:20150719.countdown.3.sql',1492953714,NULL),('phabricator:20150721.phurl.1.url.sql',1492953714,NULL),('phabricator:20150721.phurl.2.xaction.sql',1492953714,NULL),('phabricator:20150721.phurl.3.xactioncomment.sql',1492953714,NULL),('phabricator:20150721.phurl.4.url.sql',1492953714,NULL),('phabricator:20150721.phurl.5.edge.sql',1492953714,NULL),('phabricator:20150721.phurl.6.alias.sql',1492953714,NULL),('phabricator:20150721.phurl.7.authorphid.sql',1492953714,NULL),('phabricator:20150722.dashboard.1.sql',1492953714,NULL),('phabricator:20150722.dashboard.2.sql',1492953714,NULL),('phabricator:20150723.countdown.1.sql',1492953714,NULL),('phabricator:20150724.badges.comments.1.sql',1492953714,NULL),('phabricator:20150724.countdown.comments.1.sql',1492953714,NULL),('phabricator:20150725.badges.mailkey.1.sql',1492953714,NULL),('phabricator:20150725.badges.mailkey.2.php',1492953714,NULL),('phabricator:20150725.badges.viewpolicy.3.sql',1492953714,NULL),('phabricator:20150725.countdown.mailkey.1.sql',1492953714,NULL),('phabricator:20150725.countdown.mailkey.2.php',1492953714,NULL),('phabricator:20150725.slowvote.mailkey.1.sql',1492953714,NULL),('phabricator:20150725.slowvote.mailkey.2.php',1492953714,NULL),('phabricator:20150727.heraldaction.1.sql',1492953715,NULL),('phabricator:20150730.herald.1.sql',1492953715,NULL),('phabricator:20150730.herald.2.sql',1492953715,NULL),('phabricator:20150730.herald.3.sql',1492953715,NULL),('phabricator:20150730.herald.4.sql',1492953715,NULL),('phabricator:20150730.herald.5.sql',1492953715,NULL),('phabricator:20150730.herald.6.sql',1492953715,NULL),('phabricator:20150730.herald.7.sql',1492953715,NULL),('phabricator:20150803.herald.1.sql',1492953715,NULL),('phabricator:20150803.herald.2.sql',1492953715,NULL),('phabricator:20150804.ponder.answer.mailkey.1.sql',1492953715,NULL),('phabricator:20150804.ponder.answer.mailkey.2.php',1492953715,NULL),('phabricator:20150804.ponder.question.1.sql',1492953715,NULL),('phabricator:20150804.ponder.question.2.sql',1492953715,NULL),('phabricator:20150804.ponder.question.3.sql',1492953715,NULL),('phabricator:20150804.ponder.spaces.4.sql',1492953715,NULL),('phabricator:20150805.paste.status.1.sql',1492953715,NULL),('phabricator:20150805.paste.status.2.sql',1492953715,NULL),('phabricator:20150806.ponder.answer.1.sql',1492953715,NULL),('phabricator:20150806.ponder.editpolicy.2.sql',1492953715,NULL),('phabricator:20150806.ponder.status.1.sql',1492953715,NULL),('phabricator:20150806.ponder.status.2.sql',1492953715,NULL),('phabricator:20150806.ponder.status.3.sql',1492953715,NULL),('phabricator:20150808.ponder.vote.1.sql',1492953715,NULL),('phabricator:20150808.ponder.vote.2.sql',1492953715,NULL),('phabricator:20150812.ponder.answer.1.sql',1492953715,NULL),('phabricator:20150812.ponder.answer.2.sql',1492953715,NULL),('phabricator:20150814.harbormater.artifact.phid.sql',1492953715,NULL),('phabricator:20150815.owners.status.1.sql',1492953715,NULL),('phabricator:20150815.owners.status.2.sql',1492953715,NULL),('phabricator:20150823.nuance.queue.1.sql',1492953715,NULL),('phabricator:20150823.nuance.queue.2.sql',1492953715,NULL),('phabricator:20150823.nuance.queue.3.sql',1492953715,NULL),('phabricator:20150823.nuance.queue.4.sql',1492953715,NULL),('phabricator:20150828.ponder.wiki.1.sql',1492953715,NULL),('phabricator:20150829.ponder.dupe.1.sql',1492953715,NULL),('phabricator:20150904.herald.1.sql',1492953715,NULL),('phabricator:20150906.mailinglist.sql',1492953715,NULL),('phabricator:20150910.owners.custom.1.sql',1492953715,NULL),('phabricator:20150916.drydock.slotlocks.1.sql',1492953715,NULL),('phabricator:20150922.drydock.commands.1.sql',1492953715,NULL),('phabricator:20150923.drydock.resourceid.1.sql',1492953715,NULL),('phabricator:20150923.drydock.resourceid.2.sql',1492953715,NULL),('phabricator:20150923.drydock.resourceid.3.sql',1492953715,NULL),('phabricator:20150923.drydock.taskid.1.sql',1492953715,NULL),('phabricator:20150924.drydock.disable.1.sql',1492953715,NULL),('phabricator:20150924.drydock.status.1.sql',1492953715,NULL),('phabricator:20150928.drydock.rexpire.1.sql',1492953715,NULL),('phabricator:20150930.drydock.log.1.sql',1492953715,NULL),('phabricator:20151001.drydock.rname.1.sql',1492953715,NULL),('phabricator:20151002.dashboard.status.1.sql',1492953715,NULL),('phabricator:20151002.harbormaster.bparam.1.sql',1492953715,NULL),('phabricator:20151009.drydock.auth.1.sql',1492953715,NULL),('phabricator:20151010.drydock.auth.2.sql',1492953715,NULL),('phabricator:20151013.drydock.op.1.sql',1492953715,NULL),('phabricator:20151023.harborpolicy.1.sql',1492953715,NULL),('phabricator:20151023.harborpolicy.2.php',1492953715,NULL),('phabricator:20151023.patchduration.sql',1492953715,13609),('phabricator:20151030.harbormaster.initiator.sql',1492953715,29433),('phabricator:20151106.editengine.1.table.sql',1492953715,8624),('phabricator:20151106.editengine.2.xactions.sql',1492953715,6593),('phabricator:20151106.phame.post.mailkey.1.sql',1492953715,19247),('phabricator:20151106.phame.post.mailkey.2.php',1492953715,1219),('phabricator:20151107.phame.blog.mailkey.1.sql',1492953715,37986),('phabricator:20151107.phame.blog.mailkey.2.php',1492953715,704),('phabricator:20151108.phame.blog.joinpolicy.sql',1492953715,44574),('phabricator:20151108.xhpast.stderr.sql',1492953715,43870),('phabricator:20151109.phame.post.comments.1.sql',1492953716,7807),('phabricator:20151109.repository.coverage.1.sql',1492953716,943),('phabricator:20151109.xhpast.db.1.sql',1492953716,1093),('phabricator:20151109.xhpast.db.2.sql',1492953716,490),('phabricator:20151110.daemonenvhash.sql',1492953716,34291),('phabricator:20151111.phame.blog.archive.1.sql',1492953716,17601),('phabricator:20151111.phame.blog.archive.2.sql',1492953716,398),('phabricator:20151112.herald.edge.sql',1492953716,15285),('phabricator:20151116.owners.edge.sql',1492953716,13252),('phabricator:20151128.phame.blog.picture.1.sql',1492953716,18001),('phabricator:20151130.phurl.mailkey.1.sql',1492953716,11335),('phabricator:20151130.phurl.mailkey.2.php',1492953716,992),('phabricator:20151202.versioneddraft.1.sql',1492953716,7153),('phabricator:20151207.editengine.1.sql',1492953716,72200),('phabricator:20151210.land.1.refphid.sql',1492953716,13730),('phabricator:20151210.land.2.refphid.php',1492953716,629),('phabricator:20151215.phame.1.autotitle.sql',1492953716,19593),('phabricator:20151218.key.1.keyphid.sql',1492953716,14029),('phabricator:20151218.key.2.keyphid.php',1492953716,375),('phabricator:20151219.proj.01.prislug.sql',1492953716,21886),('phabricator:20151219.proj.02.prislugkey.sql',1492953716,12589),('phabricator:20151219.proj.03.copyslug.sql',1492953716,388),('phabricator:20151219.proj.04.dropslugkey.sql',1492953716,6623),('phabricator:20151219.proj.05.dropslug.sql',1492953716,19905),('phabricator:20151219.proj.06.defaultpolicy.php',1492953716,795),('phabricator:20151219.proj.07.viewnull.sql',1492953716,17884),('phabricator:20151219.proj.08.editnull.sql',1492953716,10628),('phabricator:20151219.proj.09.joinnull.sql',1492953716,9450),('phabricator:20151219.proj.10.subcolumns.sql',1492953716,141357),('phabricator:20151219.proj.11.subprojectphids.sql',1492953716,22236),('phabricator:20151221.search.1.version.sql',1492953716,10587),('phabricator:20151221.search.2.ownersngrams.sql',1492953716,7323),('phabricator:20151221.search.3.reindex.php',1492953716,310),('phabricator:20151223.proj.01.paths.sql',1492953716,21893),('phabricator:20151223.proj.02.depths.sql',1492953716,28023),('phabricator:20151223.proj.03.pathkey.sql',1492953716,13779),('phabricator:20151223.proj.04.keycol.sql',1492953716,29651),('phabricator:20151223.proj.05.updatekeys.php',1492953716,375),('phabricator:20151223.proj.06.uniq.sql',1492953716,11845),('phabricator:20151226.reop.1.sql',1492953716,17667),('phabricator:20151227.proj.01.materialize.sql',1492953716,422),('phabricator:20151231.proj.01.icon.php',1492953716,1539),('phabricator:20160102.badges.award.sql',1492953716,8429),('phabricator:20160110.repo.01.slug.sql',1492953716,36030),('phabricator:20160110.repo.02.slug.php',1492953716,407),('phabricator:20160111.repo.01.slugx.sql',1492953716,639),('phabricator:20160112.repo.01.uri.sql',1492953716,9469),('phabricator:20160112.repo.02.uri.index.php',1492953716,80),('phabricator:20160113.propanel.1.storage.sql',1492953716,6940),('phabricator:20160113.propanel.2.xaction.sql',1492953716,7108),('phabricator:20160119.project.1.silence.sql',1492953716,457),('phabricator:20160122.project.1.boarddefault.php',1492953716,755),('phabricator:20160124.people.1.icon.sql',1492953716,12766),('phabricator:20160124.people.2.icondefault.sql',1492953716,351),('phabricator:20160128.repo.1.pull.sql',1492953716,9469),('phabricator:20160201.revision.properties.1.sql',1492953716,17863),('phabricator:20160201.revision.properties.2.sql',1492953716,370),('phabricator:20160202.board.1.proxy.sql',1492953716,18324),('phabricator:20160202.ipv6.1.sql',1492953716,18661),('phabricator:20160202.ipv6.2.php',1492953716,1141),('phabricator:20160206.cover.1.sql',1492953716,40105),('phabricator:20160208.task.1.sql',1492953716,35042),('phabricator:20160208.task.2.sql',1492953716,32770),('phabricator:20160208.task.3.sql',1492953716,34137),('phabricator:20160212.proj.1.sql',1492953717,29561),('phabricator:20160212.proj.2.sql',1492953717,314),('phabricator:20160215.owners.policy.1.sql',1492953717,18003),('phabricator:20160215.owners.policy.2.sql',1492953717,17598),('phabricator:20160215.owners.policy.3.sql',1492953717,387),('phabricator:20160215.owners.policy.4.sql',1492953717,291),('phabricator:20160218.callsigns.1.sql',1492953717,10721),('phabricator:20160221.almanac.1.devicen.sql',1492953717,8045),('phabricator:20160221.almanac.2.devicei.php',1492953717,965),('phabricator:20160221.almanac.3.servicen.sql',1492953717,5846),('phabricator:20160221.almanac.4.servicei.php',1492953717,510),('phabricator:20160221.almanac.5.networkn.sql',1492953717,6516),('phabricator:20160221.almanac.6.networki.php',1492953717,813),('phabricator:20160221.almanac.7.namespacen.sql',1492953717,6664),('phabricator:20160221.almanac.8.namespace.sql',1492953717,7358),('phabricator:20160221.almanac.9.namespacex.sql',1492953717,7375),('phabricator:20160222.almanac.1.properties.php',1492953717,1075),('phabricator:20160223.almanac.1.bound.sql',1492953717,15258),('phabricator:20160223.almanac.2.lockbind.sql',1492953717,363),('phabricator:20160223.almanac.3.devicelock.sql',1492953717,28139),('phabricator:20160223.almanac.4.servicelock.sql',1492953717,24328),('phabricator:20160223.paste.fileedges.php',1492953717,552),('phabricator:20160225.almanac.1.disablebinding.sql',1492953717,20577),('phabricator:20160225.almanac.2.stype.sql',1492953717,6572),('phabricator:20160225.almanac.3.stype.php',1492953717,402),('phabricator:20160227.harbormaster.1.plann.sql',1492953717,8121),('phabricator:20160227.harbormaster.2.plani.php',1492953717,346),('phabricator:20160303.drydock.1.bluen.sql',1492953717,7287),('phabricator:20160303.drydock.2.bluei.php',1492953717,355),('phabricator:20160303.drydock.3.edge.sql',1492953717,13334),('phabricator:20160308.nuance.01.disabled.sql',1492953717,16961),('phabricator:20160308.nuance.02.cursordata.sql',1492953717,7361),('phabricator:20160308.nuance.03.sourcen.sql',1492953717,6397),('phabricator:20160308.nuance.04.sourcei.php',1492953717,1144),('phabricator:20160308.nuance.05.sourcename.sql',1492953717,11455),('phabricator:20160308.nuance.06.label.sql',1492953717,18960),('phabricator:20160308.nuance.07.itemtype.sql',1492953717,22009),('phabricator:20160308.nuance.08.itemkey.sql',1492953717,21836),('phabricator:20160308.nuance.09.itemcontainer.sql',1492953717,21988),('phabricator:20160308.nuance.10.itemkeyu.sql',1492953717,366),('phabricator:20160308.nuance.11.requestor.sql',1492953717,11681),('phabricator:20160308.nuance.12.queue.sql',1492953717,22125),('phabricator:20160316.lfs.01.token.resource.sql',1492953717,19283),('phabricator:20160316.lfs.02.token.user.sql',1492953717,16100),('phabricator:20160316.lfs.03.token.properties.sql',1492953717,16663),('phabricator:20160316.lfs.04.token.default.sql',1492953717,369),('phabricator:20160317.lfs.01.ref.sql',1492953717,6665),('phabricator:20160321.nuance.01.taskbridge.sql',1492953717,29611),('phabricator:20160322.nuance.01.itemcommand.sql',1492953717,8177),('phabricator:20160323.badgemigrate.sql',1492953717,884),('phabricator:20160329.nuance.01.requestor.sql',1492953717,1317),('phabricator:20160329.nuance.02.requestorsource.sql',1492953717,1457),('phabricator:20160329.nuance.03.requestorxaction.sql',1492953717,1284),('phabricator:20160329.nuance.04.requestorcomment.sql',1492953717,1283),('phabricator:20160330.badges.migratequality.sql',1492953717,11262),('phabricator:20160330.badges.qualityxaction.mig.sql',1492953717,1541),('phabricator:20160331.fund.comments.1.sql',1492953717,6270),('phabricator:20160404.oauth.1.xaction.sql',1492953717,5694),('phabricator:20160405.oauth.2.disable.sql',1492953717,13715),('phabricator:20160406.badges.ngrams.php',1492953717,526),('phabricator:20160406.badges.ngrams.sql',1492953717,11583),('phabricator:20160406.columns.1.php',1492953717,524),('phabricator:20160411.repo.1.version.sql',1492953717,5926),('phabricator:20160418.repouri.1.sql',1492953717,5583),('phabricator:20160418.repouri.2.sql',1492953717,10524),('phabricator:20160418.repoversion.1.sql',1492953717,13780),('phabricator:20160419.pushlog.1.sql',1492953717,25781),('phabricator:20160424.locks.1.sql',1492953717,14645),('phabricator:20160426.searchedge.sql',1492953717,13370),('phabricator:20160428.repo.1.urixaction.sql',1492953717,7961),('phabricator:20160503.repo.01.lpath.sql',1492953717,62415),('phabricator:20160503.repo.02.lpathkey.sql',1492953717,25518),('phabricator:20160503.repo.03.lpathmigrate.php',1492953717,556),('phabricator:20160503.repo.04.mirrormigrate.php',1492953717,493),('phabricator:20160503.repo.05.urimigrate.php',1492953717,418),('phabricator:20160510.repo.01.uriindex.php',1492953717,4833),('phabricator:20160513.owners.01.autoreview.sql',1492953717,32054),('phabricator:20160513.owners.02.autoreviewnone.sql',1492953717,499),('phabricator:20160516.owners.01.dominion.sql',1492953717,14616),('phabricator:20160516.owners.02.dominionstrong.sql',1492953717,323),('phabricator:20160517.oauth.01.edge.sql',1492953717,11629),('phabricator:20160518.ssh.01.activecol.sql',1492953717,13262),('phabricator:20160518.ssh.02.activeval.sql',1492953717,282),('phabricator:20160518.ssh.03.activekey.sql',1492953717,21706),('phabricator:20160519.ssh.01.xaction.sql',1492953717,8147),('phabricator:20160531.pref.01.xaction.sql',1492953717,6483),('phabricator:20160531.pref.02.datecreatecol.sql',1492953717,12856),('phabricator:20160531.pref.03.datemodcol.sql',1492953717,14408),('phabricator:20160531.pref.04.datecreateval.sql',1492953717,367),('phabricator:20160531.pref.05.datemodval.sql',1492953717,298),('phabricator:20160531.pref.06.phidcol.sql',1492953717,16950),('phabricator:20160531.pref.07.phidval.php',1492953717,603),('phabricator:20160601.user.01.cache.sql',1492953717,8935),('phabricator:20160601.user.02.copyprefs.php',1492953717,1423),('phabricator:20160601.user.03.removetime.sql',1492953718,20344),('phabricator:20160601.user.04.removetranslation.sql',1492953718,21336),('phabricator:20160601.user.05.removesex.sql',1492953718,23471),('phabricator:20160603.user.01.removedcenabled.sql',1492953718,20090),('phabricator:20160603.user.02.removedctab.sql',1492953718,19685),('phabricator:20160603.user.03.removedcvisible.sql',1492953718,20696),('phabricator:20160604.user.01.stringmailprefs.php',1492953718,457),('phabricator:20160604.user.02.removeimagecache.sql',1492953718,19842),('phabricator:20160605.user.01.prefnulluser.sql',1492953718,12045),('phabricator:20160605.user.02.prefbuiltin.sql',1492953718,12871),('phabricator:20160605.user.03.builtinunique.sql',1492953718,10637),('phabricator:20160616.phame.blog.header.1.sql',1492953718,15672),('phabricator:20160616.repo.01.oldref.sql',1492953718,6173),('phabricator:20160617.harbormaster.01.arelease.sql',1492953718,15915),('phabricator:20160618.phame.blog.subtitle.sql',1492953718,15243),('phabricator:20160620.phame.blog.parentdomain.2.sql',1492953718,15398),('phabricator:20160620.phame.blog.parentsite.1.sql',1492953718,16059),('phabricator:20160623.phame.blog.fulldomain.1.sql',1492953718,16216),('phabricator:20160623.phame.blog.fulldomain.2.sql',1492953718,383),('phabricator:20160623.phame.blog.fulldomain.3.sql',1492953718,427),('phabricator:20160706.phame.blog.parentdomain.2.sql',1492953718,17416),('phabricator:20160706.phame.blog.parentsite.1.sql',1492953718,15821),('phabricator:20160707.calendar.01.stub.sql',1492953718,15805),('phabricator:20160711.files.01.builtin.sql',1492953718,23541),('phabricator:20160711.files.02.builtinkey.sql',1492953718,11421),('phabricator:20160713.event.01.host.sql',1492953718,11510),('phabricator:20160715.event.01.alldayfrom.sql',1492953718,17347),('phabricator:20160715.event.02.alldayto.sql',1492953718,16771),('phabricator:20160715.event.03.allday.php',1492953718,65),('phabricator:20160720.calendar.invitetxn.php',1492953718,1008),('phabricator:20160721.pack.01.pub.sql',1492953718,11271),('phabricator:20160721.pack.02.pubxaction.sql',1492953718,7364),('phabricator:20160721.pack.03.edge.sql',1492953718,13426),('phabricator:20160721.pack.04.pkg.sql',1492953718,7126),('phabricator:20160721.pack.05.pkgxaction.sql',1492953718,6996),('phabricator:20160721.pack.06.version.sql',1492953718,7508),('phabricator:20160721.pack.07.versionxaction.sql',1492953718,7013),('phabricator:20160722.pack.01.pubngrams.sql',1492953718,6915),('phabricator:20160722.pack.02.pkgngrams.sql',1492953718,7627),('phabricator:20160722.pack.03.versionngrams.sql',1492953718,7239),('phabricator:20160810.commit.01.summarylength.sql',1492953718,11762),('phabricator:20160824.connectionlog.sql',1492953718,1222),('phabricator:20160824.repohint.01.hint.sql',1492953718,6193),('phabricator:20160824.repohint.02.movebad.php',1492953718,459),('phabricator:20160824.repohint.03.nukebad.sql',1492953718,1167),('phabricator:20160825.ponder.sql',1492953718,719),('phabricator:20160829.pastebin.01.language.sql',1492953718,10809),('phabricator:20160829.pastebin.02.language.sql',1492953718,514),('phabricator:20160913.conpherence.topic.1.sql',1492953718,11994),('phabricator:20160919.repo.messagecount.sql',1492953718,13142),('phabricator:20160919.repo.messagedefault.sql',1492953718,7514),('phabricator:20160921.fileexternalrequest.sql',1492953718,6953),('phabricator:20160927.phurl.ngrams.php',1492953718,375),('phabricator:20160927.phurl.ngrams.sql',1492953718,7421),('phabricator:20160928.repo.messagecount.sql',1492953718,381),('phabricator:20160928.tokentoken.sql',1492953718,6865),('phabricator:20161003.cal.01.utcepoch.sql',1492953718,55561),('phabricator:20161003.cal.02.parameters.sql',1492953718,16511),('phabricator:20161004.cal.01.noepoch.php',1492953718,1626),('phabricator:20161005.cal.01.rrules.php',1492953718,276),('phabricator:20161005.cal.02.export.sql',1492953718,9626),('phabricator:20161005.cal.03.exportxaction.sql',1492953718,7148),('phabricator:20161005.conpherence.image.1.sql',1492953718,12044),('phabricator:20161005.conpherence.image.2.php',1492953718,340),('phabricator:20161011.conpherence.ngrams.php',1492953718,313),('phabricator:20161011.conpherence.ngrams.sql',1492953718,9940),('phabricator:20161012.cal.01.import.sql',1492953718,7387),('phabricator:20161012.cal.02.importxaction.sql',1492953718,6560),('phabricator:20161012.cal.03.eventimport.sql',1492953718,64748),('phabricator:20161013.cal.01.importlog.sql',1492953718,6363),('phabricator:20161016.conpherence.imagephids.sql',1492953718,11462),('phabricator:20161025.phortune.contact.1.sql',1492953718,13783),('phabricator:20161025.phortune.merchant.image.1.sql',1492953718,12905),('phabricator:20161026.calendar.01.importtriggers.sql',1492953718,29953),('phabricator:20161027.calendar.01.externalinvitee.sql',1492953718,7485),('phabricator:20161029.phortune.invoice.1.sql',1492953718,36760),('phabricator:20161031.calendar.01.seriesparent.sql',1492953718,18327),('phabricator:20161031.calendar.02.notifylog.sql',1492953718,8078),('phabricator:20161101.calendar.01.noholiday.sql',1492953718,1286),('phabricator:20161101.calendar.02.removecolumns.sql',1492953719,93220),('phabricator:20161104.calendar.01.availability.sql',1492953719,16257),('phabricator:20161104.calendar.02.availdefault.sql',1492953719,430),('phabricator:20161115.phamepost.01.subtitle.sql',1492953719,17588),('phabricator:20161115.phamepost.02.header.sql',1492953719,16388),('phabricator:20161121.cluster.01.hoststate.sql',1492953719,8311),('phabricator:20161124.search.01.stopwords.sql',1492953719,7121),('phabricator:20161125.search.01.stemmed.sql',1492953719,6287),('phabricator:20161130.search.01.manual.sql',1492953719,6232),('phabricator:20161130.search.02.rebuild.php',1492953719,2751),('phabricator:20161210.dashboards.01.author.sql',1492953719,12555),('phabricator:20161210.dashboards.02.author.php',1492953719,746),('phabricator:20161211.menu.01.itemkey.sql',1492953719,8205),('phabricator:20161211.menu.02.itemprops.sql',1492953719,6574),('phabricator:20161211.menu.03.order.sql',1492953719,5930),('phabricator:20161212.dashboardpanel.01.author.sql',1492953719,11841),('phabricator:20161212.dashboardpanel.02.author.php',1492953719,730),('phabricator:20161212.dashboards.01.icon.sql',1492953719,14194),('phabricator:20161213.diff.01.hunks.php',1492953719,542),('phabricator:20161216.dashboard.ngram.01.sql',1492953719,15640),('phabricator:20161216.dashboard.ngram.02.php',1492953719,675),('phabricator:20170106.menu.01.customphd.sql',1492953719,12003),('phabricator:20170109.diff.01.commit.sql',1492953719,16340),('phabricator:20170119.menuitem.motivator.01.php',1492953719,219),('phabricator:20170131.dashboard.personal.01.php',1492953719,672),('phabricator:20170301.subtype.01.col.sql',1492953719,16642),('phabricator:20170301.subtype.02.default.sql',1492953719,394),('phabricator:20170301.subtype.03.taskcol.sql',1492953719,28706),('phabricator:20170301.subtype.04.taskdefault.sql',1492953719,375),('phabricator:20170303.people.01.avatar.sql',1492953719,50756),('phabricator:20170313.reviewers.01.sql',1492953719,9787),('phabricator:20170316.rawfiles.01.php',1492953719,993),('phabricator:20170320.reviewers.01.lastaction.sql',1492953719,13463),('phabricator:20170320.reviewers.02.lastcomment.sql',1492953719,16623),('phabricator:20170320.reviewers.03.migrate.php',1492953719,787),('phabricator:20170322.reviewers.04.actor.sql',1492953719,19471),('phabricator:20170328.reviewers.01.void.sql',1492953719,15326),('phabricator:20170406.hmac.01.keystore.sql',1492953719,7476),('phabricator:20170410.calendar.01.repair.php',1492953719,445),('phabricator:20170412.conpherence.01.picturecrop.sql',1492953719,287),('phabricator:20170413.conpherence.01.recentparty.sql',1492953719,11813),('phabricator:20170417.files.ngrams.sql',1492953719,9145),('phabricator:20170418.1.application.01.xaction.sql',1492953719,6782),('phabricator:20170418.1.application.02.edge.sql',1492953719,12783),('phabricator:20170418.files.isDeleted.sql',1492953719,26882),('phabricator:20170419.app.01.table.sql',1492953719,9433),('phabricator:20170419.thread.01.behind.sql',1492953719,17501),('phabricator:20170419.thread.02.status.sql',1492953719,18503),('phabricator:20170419.thread.03.touched.sql',1492953719,20247),('phabricator:daemonstatus.sql',1492953704,NULL),('phabricator:daemonstatuskey.sql',1492953704,NULL),('phabricator:daemontaskarchive.sql',1492953705,NULL),('phabricator:db.almanac',1492953699,NULL),('phabricator:db.application',1492953699,NULL),('phabricator:db.audit',1492953699,NULL),('phabricator:db.auth',1492953699,NULL),('phabricator:db.badges',1492953699,NULL),('phabricator:db.cache',1492953699,NULL),('phabricator:db.calendar',1492953699,NULL),('phabricator:db.chatlog',1492953699,NULL),('phabricator:db.conduit',1492953699,NULL),('phabricator:db.config',1492953699,NULL),('phabricator:db.conpherence',1492953699,NULL),('phabricator:db.countdown',1492953699,NULL),('phabricator:db.daemon',1492953699,NULL),('phabricator:db.dashboard',1492953699,NULL),('phabricator:db.differential',1492953699,NULL),('phabricator:db.diviner',1492953699,NULL),('phabricator:db.doorkeeper',1492953699,NULL),('phabricator:db.draft',1492953699,NULL),('phabricator:db.drydock',1492953699,NULL),('phabricator:db.fact',1492953699,NULL),('phabricator:db.feed',1492953699,NULL),('phabricator:db.file',1492953699,NULL),('phabricator:db.flag',1492953699,NULL),('phabricator:db.fund',1492953699,NULL),('phabricator:db.harbormaster',1492953699,NULL),('phabricator:db.herald',1492953699,NULL),('phabricator:db.legalpad',1492953699,NULL),('phabricator:db.maniphest',1492953699,NULL),('phabricator:db.meta_data',1492953699,NULL),('phabricator:db.metamta',1492953699,NULL),('phabricator:db.multimeter',1492953699,NULL),('phabricator:db.nuance',1492953699,NULL),('phabricator:db.oauth_server',1492953699,NULL),('phabricator:db.owners',1492953699,NULL),('phabricator:db.packages',1492953699,NULL),('phabricator:db.passphrase',1492953699,NULL),('phabricator:db.pastebin',1492953699,NULL),('phabricator:db.phame',1492953699,NULL),('phabricator:db.phlux',1492953699,NULL),('phabricator:db.pholio',1492953699,NULL),('phabricator:db.phortune',1492953699,NULL),('phabricator:db.phragment',1492953699,NULL),('phabricator:db.phrequent',1492953699,NULL),('phabricator:db.phriction',1492953699,NULL),('phabricator:db.phurl',1492953699,NULL),('phabricator:db.policy',1492953699,NULL),('phabricator:db.ponder',1492953699,NULL),('phabricator:db.project',1492953699,NULL),('phabricator:db.releeph',1492953699,NULL),('phabricator:db.repository',1492953699,NULL),('phabricator:db.search',1492953699,NULL),('phabricator:db.slowvote',1492953699,NULL),('phabricator:db.spaces',1492953699,NULL),('phabricator:db.system',1492953699,NULL),('phabricator:db.timeline',1492953699,NULL),('phabricator:db.token',1492953699,NULL),('phabricator:db.user',1492953699,NULL),('phabricator:db.worker',1492953699,NULL),('phabricator:db.xhpast',1492953699,NULL),('phabricator:db.xhpastview',1492953699,NULL),('phabricator:db.xhprof',1492953699,NULL),('phabricator:differentialbookmarks.sql',1492953704,NULL),('phabricator:draft-metadata.sql',1492953704,NULL),('phabricator:dropfileproxyimage.sql',1492953705,NULL),('phabricator:drydockresoucetype.sql',1492953705,NULL),('phabricator:drydocktaskid.sql',1492953705,NULL),('phabricator:edgetype.sql',1492953704,NULL),('phabricator:emailtable.sql',1492953704,NULL),('phabricator:emailtableport.sql',1492953704,NULL),('phabricator:emailtableremove.sql',1492953704,NULL),('phabricator:fact-raw.sql',1492953704,NULL),('phabricator:harbormasterobject.sql',1492953704,NULL),('phabricator:holidays.sql',1492953704,NULL),('phabricator:ldapinfo.sql',1492953704,NULL),('phabricator:legalpad-mailkey-populate.php',1492953706,NULL),('phabricator:legalpad-mailkey.sql',1492953706,NULL),('phabricator:liskcounters-task.sql',1492953705,NULL),('phabricator:liskcounters.php',1492953705,NULL),('phabricator:liskcounters.sql',1492953705,NULL),('phabricator:maniphestxcache.sql',1492953704,NULL),('phabricator:markupcache.sql',1492953704,NULL),('phabricator:migrate-differential-dependencies.php',1492953704,NULL),('phabricator:migrate-maniphest-dependencies.php',1492953704,NULL),('phabricator:migrate-maniphest-revisions.php',1492953704,NULL),('phabricator:migrate-project-edges.php',1492953704,NULL),('phabricator:owners-exclude.sql',1492953705,NULL),('phabricator:pastepolicy.sql',1492953704,NULL),('phabricator:phameblog.sql',1492953704,NULL),('phabricator:phamedomain.sql',1492953704,NULL),('phabricator:phameoneblog.sql',1492953705,NULL),('phabricator:phamepolicy.sql',1492953704,NULL),('phabricator:phiddrop.sql',1492953704,NULL),('phabricator:pholio.sql',1492953705,NULL),('phabricator:policy-project.sql',1492953704,NULL),('phabricator:ponder-comments.sql',1492953704,NULL),('phabricator:ponder-mailkey-populate.php',1492953704,NULL),('phabricator:ponder-mailkey.sql',1492953704,NULL),('phabricator:ponder.sql',1492953704,NULL),('phabricator:releeph.sql',1492953705,NULL),('phabricator:repository-lint.sql',1492953705,NULL),('phabricator:statustxt.sql',1492953705,NULL),('phabricator:symbolcontexts.sql',1492953704,NULL),('phabricator:testdatabase.sql',1492953704,NULL),('phabricator:threadtopic.sql',1492953704,NULL),('phabricator:userstatus.sql',1492953704,NULL),('phabricator:usertranslation.sql',1492953704,NULL),('phabricator:xhprof.sql',1492953704,NULL);

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
  `spacePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_address` (`address`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_application` (`applicationPHID`),
  KEY `key_space` (`spacePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `metamta_applicationemailtransaction` (
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

CREATE TABLE `metamta_mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `actorPHID` varbinary(64) DEFAULT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `message` longtext COLLATE {$COLLATE_TEXT},
  `relatedPHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `relatedPHID` (`relatedPHID`),
  KEY `key_created` (`dateCreated`),
  KEY `key_actorPHID` (`actorPHID`),
  KEY `status` (`status`)
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
  `isDisabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `creatorPHID` (`creatorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `oauth_server_transaction` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_owners` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_owners`;

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

CREATE TABLE `owners_customfieldnumericindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`),
  KEY `key_find` (`indexKey`,`indexValue`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `owners_customfieldstorage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `fieldIndex` binary(12) NOT NULL,
  `fieldValue` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objectPHID` (`objectPHID`,`fieldIndex`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `owners_customfieldstringindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `indexKey` binary(12) NOT NULL,
  `indexValue` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_join` (`objectPHID`,`indexKey`,`indexValue`(64)),
  KEY `key_find` (`indexKey`,`indexValue`(64))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `owners_name_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

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
  `name` varchar(128) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `originalName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `primaryOwnerPHID` varbinary(64) DEFAULT NULL,
  `auditingEnabled` tinyint(1) NOT NULL DEFAULT '0',
  `mailKey` binary(20) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `autoReview` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dominion` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `owners_packagetransaction` (
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
  `language` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `parentPHID` varbinary(64) DEFAULT NULL,
  `viewPolicy` varbinary(64) DEFAULT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `parentPHID` (`parentPHID`),
  KEY `authorPHID` (`authorPHID`),
  KEY `key_dateCreated` (`dateCreated`),
  KEY `key_language` (`language`),
  KEY `key_space` (`spacePHID`)
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
  `mailKey` binary(20) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `headerImagePHID` varbinary(64) DEFAULT NULL,
  `subtitle` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parentDomain` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `parentSite` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `domainFullURI` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phame_blogtransaction` (
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

CREATE TABLE `phame_post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `bloggerPHID` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `phameTitle` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} DEFAULT NULL,
  `body` longtext COLLATE {$COLLATE_TEXT},
  `visibility` int(10) unsigned NOT NULL DEFAULT '0',
  `configData` longtext COLLATE {$COLLATE_TEXT},
  `datePublished` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `blogPHID` varbinary(64) DEFAULT NULL,
  `mailKey` binary(20) NOT NULL,
  `subtitle` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `headerImagePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `bloggerPosts` (`bloggerPHID`,`visibility`,`datePublished`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phame_posttransaction` (
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

CREATE TABLE `phame_posttransaction_comment` (
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
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `joinPolicy` varbinary(64) NOT NULL,
  `isMembershipLocked` tinyint(1) NOT NULL DEFAULT '0',
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `icon` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `color` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `primarySlug` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `parentProjectPHID` varbinary(64) DEFAULT NULL,
  `hasWorkboard` tinyint(1) NOT NULL,
  `hasMilestones` tinyint(1) NOT NULL,
  `hasSubprojects` tinyint(1) NOT NULL,
  `milestoneNumber` int(10) unsigned DEFAULT NULL,
  `projectPath` varbinary(64) NOT NULL,
  `projectDepth` int(10) unsigned NOT NULL,
  `projectPathKey` binary(4) NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_pathkey` (`projectPathKey`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_primaryslug` (`primarySlug`),
  UNIQUE KEY `key_milestone` (`parentProjectPHID`,`milestoneNumber`),
  KEY `key_icon` (`icon`),
  KEY `key_color` (`color`),
  KEY `key_path` (`projectPath`,`projectDepth`)
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
  `proxyPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_proxy` (`projectPHID`,`proxyPHID`),
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
  `callsign` varchar(32) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} DEFAULT NULL,
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
  `spacePHID` varbinary(64) DEFAULT NULL,
  `repositorySlug` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} DEFAULT NULL,
  `localPath` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `callsign` (`callsign`),
  UNIQUE KEY `key_slug` (`repositorySlug`),
  UNIQUE KEY `key_local` (`localPath`),
  KEY `key_vcs` (`versionControlSystem`),
  KEY `key_name` (`name`(128)),
  KEY `key_space` (`spacePHID`)
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
  `summary` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `importStatus` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_commit_identity` (`commitIdentifier`,`repositoryID`),
  KEY `repositoryID_2` (`repositoryID`,`epoch`),
  KEY `authorPHID` (`authorPHID`,`auditStatus`,`epoch`),
  KEY `repositoryID` (`repositoryID`,`importStatus`),
  KEY `key_epoch` (`epoch`),
  KEY `key_author` (`authorPHID`,`epoch`)
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

CREATE TABLE `repository_commithint` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varbinary(64) NOT NULL,
  `oldCommitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  `newCommitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `hintType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_old` (`repositoryPHID`,`oldCommitIdentifier`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_coverage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branchID` int(10) unsigned NOT NULL,
  `commitID` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `coverage` longblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_path` (`branchID`,`pathID`,`commitID`)
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

CREATE TABLE `repository_gitlfsref` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varbinary(64) NOT NULL,
  `objectHash` binary(64) NOT NULL,
  `byteSize` bigint(20) unsigned NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `filePHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`repositoryPHID`,`objectHash`)
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

CREATE TABLE `repository_oldref` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varbinary(64) NOT NULL,
  `commitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
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

CREATE TABLE `repository_pullevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) DEFAULT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `pullerPHID` varbinary(64) DEFAULT NULL,
  `remoteAddress` varbinary(64) DEFAULT NULL,
  `remoteProtocol` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `resultType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `resultCode` int(10) unsigned NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_repository` (`repositoryPHID`),
  KEY `key_epoch` (`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_pushevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `pusherPHID` varbinary(64) NOT NULL,
  `remoteAddress` varbinary(64) DEFAULT NULL,
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
  `devicePHID` varbinary(64) DEFAULT NULL,
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
  `phid` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `refType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `refNameHash` binary(12) NOT NULL,
  `refNameRaw` longblob NOT NULL,
  `refNameEncoding` varchar(16) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `commitIdentifier` varchar(40) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isClosed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_cursor` (`repositoryPHID`,`refType`,`refNameHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_statusmessage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `statusType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `statusCode` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `messageCount` int(10) unsigned NOT NULL DEFAULT '0',
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
  `repositoryPHID` varbinary(64) NOT NULL,
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

CREATE TABLE `repository_uri` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) NOT NULL,
  `uri` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `builtinProtocol` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `builtinIdentifier` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `ioType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `displayType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `credentialPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_builtin` (`repositoryPHID`,`builtinProtocol`,`builtinIdentifier`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_uriindex` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varbinary(64) NOT NULL,
  `repositoryURI` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_repository` (`repositoryPHID`),
  KEY `key_uri` (`repositoryURI`(128))
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_uritransaction` (
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

CREATE TABLE `repository_vcspassword` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `passwordHash` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `repository_workingcopyversion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varbinary(64) NOT NULL,
  `devicePHID` varbinary(64) NOT NULL,
  `repositoryVersion` int(10) unsigned NOT NULL,
  `isWriting` tinyint(1) NOT NULL,
  `writeProperties` longtext COLLATE {$COLLATE_TEXT},
  `lockOwner` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_workingcopy` (`repositoryPHID`,`devicePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_search` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_search`;

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

CREATE TABLE `search_document` (
  `phid` varbinary(64) NOT NULL,
  `documentType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `documentTitle` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `documentCreated` int(10) unsigned NOT NULL,
  `documentModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`phid`),
  KEY `documentCreated` (`documentCreated`),
  KEY `key_type` (`documentType`,`documentCreated`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_documentfield` (
  `phid` varbinary(64) NOT NULL,
  `phidType` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `field` varchar(4) COLLATE {$COLLATE_TEXT} NOT NULL,
  `auxPHID` varbinary(64) DEFAULT NULL,
  `corpus` longtext CHARACTER SET {$CHARSET_FULLTEXT} COLLATE {$COLLATE_FULLTEXT},
  `stemmedCorpus` longtext CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT},
  KEY `phid` (`phid`),
  FULLTEXT KEY `key_corpus` (`corpus`,`stemmedCorpus`)
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

CREATE TABLE `search_editengineconfiguration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `engineKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `builtinKey` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isDisabled` tinyint(1) NOT NULL DEFAULT '0',
  `isDefault` tinyint(1) NOT NULL DEFAULT '0',
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `isEdit` tinyint(1) NOT NULL,
  `createOrder` int(10) unsigned NOT NULL,
  `editOrder` int(10) unsigned NOT NULL,
  `subtype` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_engine` (`engineKey`,`builtinKey`),
  KEY `key_default` (`engineKey`,`isDefault`,`isDisabled`),
  KEY `key_edit` (`engineKey`,`isEdit`,`isDisabled`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_editengineconfigurationtransaction` (
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

CREATE TABLE `search_indexversion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectPHID` varbinary(64) NOT NULL,
  `extensionKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `version` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_object` (`objectPHID`,`extensionKey`)
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

CREATE TABLE `search_profilepanelconfiguration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `profilePHID` varbinary(64) NOT NULL,
  `menuItemKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `builtinKey` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `menuItemOrder` int(10) unsigned DEFAULT NULL,
  `visibility` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `menuItemProperties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `customPHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_profile` (`profilePHID`,`menuItemOrder`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `search_profilepanelconfigurationtransaction` (
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

CREATE TABLE `stopwords` (
  `value` varchar(32) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

INSERT INTO `stopwords` VALUES ('the'),('be'),('and'),('of'),('a'),('in'),('to'),('have'),('it'),('I'),('that'),('for'),('you'),('he'),('with'),('on'),('do'),('say'),('this'),('they'),('at'),('but'),('we'),('his'),('from'),('not'),('by'),('or'),('as'),('what'),('go'),('their'),('can'),('who'),('get'),('if'),('would'),('all'),('my'),('will'),('up'),('there'),('so'),('its'),('us');

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
  `spacePHID` varbinary(64) DEFAULT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `key_space` (`spacePHID`)
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
  `passwordSalt` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `passwordHash` varchar(128) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `conduitCertificate` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `isSystemAgent` tinyint(1) NOT NULL DEFAULT '0',
  `isDisabled` tinyint(1) NOT NULL,
  `isAdmin` tinyint(1) NOT NULL,
  `isEmailVerified` int(10) unsigned NOT NULL,
  `isApproved` int(10) unsigned NOT NULL,
  `accountSecret` binary(64) NOT NULL,
  `isEnrolledInMultiFactor` tinyint(1) NOT NULL DEFAULT '0',
  `availabilityCache` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `availabilityCacheTTL` int(10) unsigned DEFAULT NULL,
  `isMailingList` tinyint(1) NOT NULL,
  `defaultProfileImagePHID` varbinary(64) DEFAULT NULL,
  `defaultProfileImageVersion` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
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

CREATE TABLE `user_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `cacheIndex` binary(12) NOT NULL,
  `cacheKey` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `cacheData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `cacheType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_usercache` (`userPHID`,`cacheIndex`),
  KEY `key_cachekey` (`cacheIndex`),
  KEY `key_cachetype` (`cacheType`)
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
  UNIQUE KEY `account_details` (`accountType`,`accountDomain`,`accountID`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_user` (`userPHID`)
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
  `userPHID` varbinary(64) DEFAULT NULL,
  `preferences` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varbinary(64) NOT NULL,
  `builtinKey` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_builtin` (`builtinKey`),
  UNIQUE KEY `key_user` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `user_preferencestransaction` (
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

CREATE TABLE `user_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `blurb` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `icon` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
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
  KEY `key_modified` (`dateModified`),
  KEY `key_object` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `worker_bulkjob` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `jobTypeKey` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_type` (`jobTypeKey`),
  KEY `key_author` (`authorPHID`),
  KEY `key_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `worker_bulkjobtransaction` (
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

CREATE TABLE `worker_bulktask` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bulkJobPHID` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_job` (`bulkJobPHID`,`status`),
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_xhpast` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_xhpast`;

CREATE TABLE `xhpast_parsetree` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varbinary(64) DEFAULT NULL,
  `input` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `returnCode` int(10) NOT NULL,
  `stdout` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `stderr` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
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
  `mailKey` binary(20) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `key_oneanswerperquestion` (`questionID`,`authorPHID`),
  KEY `questionID` (`questionID`),
  KEY `authorPHID` (`authorPHID`),
  KEY `status` (`status`)
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
  `authorPHID` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `content` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `contentSource` longtext COLLATE {$COLLATE_TEXT},
  `answerCount` int(10) unsigned NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  `answerWiki` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`),
  KEY `status` (`status`),
  KEY `key_space` (`spacePHID`)
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
  `spacePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`),
  KEY `key_space` (`spacePHID`)
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
  `seenMessageCount` bigint(20) unsigned NOT NULL,
  `settings` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conpherencePHID` (`conpherencePHID`,`participantPHID`),
  KEY `key_thread` (`participantPHID`,`conpherencePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conpherence_thread` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `title` varchar(255) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `messageCount` bigint(20) unsigned NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `joinPolicy` varbinary(64) NOT NULL,
  `mailKey` varchar(20) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `topic` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `conpherence_threadtitle_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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

CREATE TABLE `config_manualactivity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activityType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_type` (`activityType`)
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

CREATE TABLE `token_token` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `flavor` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `builtinKey` varchar(32) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `creatorPHID` varbinary(64) NOT NULL,
  `tokenImagePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_builtin` (`builtinKey`),
  KEY `key_creator` (`creatorPHID`,`dateModified`)
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
  `isInvoice` tinyint(1) NOT NULL,
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
  `contactInfo` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `profileImagePHID` varbinary(64) DEFAULT NULL,
  `invoiceEmail` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `invoiceFooter` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
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
  `repositoryPHID` varbinary(64) DEFAULT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `configurationData` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `diviner_livebooktransaction` (
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

CREATE TABLE `diviner_livesymbol` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `bookPHID` varbinary(64) NOT NULL,
  `repositoryPHID` varbinary(64) DEFAULT NULL,
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

CREATE TABLE `auth_hmackey` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `keyName` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyValue` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`keyName`)
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
  `phid` varbinary(64) NOT NULL,
  `objectPHID` varbinary(64) NOT NULL,
  `name` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyType` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyBody` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `keyComment` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `keyIndex` binary(12) NOT NULL,
  `isTrusted` tinyint(1) NOT NULL,
  `isActive` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_activeunique` (`keyIndex`,`isActive`),
  KEY `key_object` (`objectPHID`),
  KEY `key_active` (`isActive`,`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `auth_sshkeytransaction` (
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

CREATE TABLE `auth_temporarytoken` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tokenResource` varbinary(64) NOT NULL,
  `tokenType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `tokenExpires` int(10) unsigned NOT NULL,
  `tokenCode` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `userPHID` varbinary(64) DEFAULT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_token` (`tokenResource`,`tokenType`,`tokenCode`),
  KEY `key_expires` (`tokenExpires`),
  KEY `key_user` (`userPHID`)
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

CREATE TABLE `nuance_importcursordata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `sourcePHID` varbinary(64) NOT NULL,
  `cursorKey` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `cursorType` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_source` (`sourcePHID`,`cursorKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `ownerPHID` varbinary(64) DEFAULT NULL,
  `requestorPHID` varbinary(64) DEFAULT NULL,
  `sourcePHID` varbinary(64) NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `queuePHID` varbinary(64) DEFAULT NULL,
  `itemType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `itemKey` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `itemContainerKey` varchar(64) COLLATE {$COLLATE_TEXT} DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_item` (`sourcePHID`,`itemKey`),
  KEY `key_source` (`sourcePHID`,`status`),
  KEY `key_owner` (`ownerPHID`,`status`),
  KEY `key_requestor` (`requestorPHID`,`status`),
  KEY `key_queue` (`queuePHID`,`status`),
  KEY `key_container` (`sourcePHID`,`itemContainerKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_itemcommand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `itemPHID` varbinary(64) NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `command` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `parameters` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_item` (`itemPHID`)
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

CREATE TABLE `nuance_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `type` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `data` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `defaultQueuePHID` varbinary(64) NOT NULL,
  `isDisabled` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_type` (`type`,`dateModified`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `nuance_sourcename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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
  `authorPHID` varbinary(64) NOT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_secret` (`secretID`),
  KEY `key_type` (`credentialType`),
  KEY `key_provides` (`providesType`),
  KEY `key_space` (`spacePHID`)
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
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `layoutConfig` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `icon` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `dashboard_dashboard_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `dashboard_dashboardpanel_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `panelType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `isArchived` tinyint(1) NOT NULL DEFAULT '0',
  `properties` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `authorPHID` varbinary(64) NOT NULL,
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

CREATE TABLE `fund_initiativetransaction_comment` (
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
  `isDisabled` tinyint(1) NOT NULL,
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
  `isBoundToClusterService` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`nameIndex`),
  KEY `key_nametext` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_devicename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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

CREATE TABLE `almanac_namespace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(128) COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameIndex` binary(12) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_nameindex` (`nameIndex`),
  KEY `key_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_namespacename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_namespacetransaction` (
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

CREATE TABLE `almanac_networkname_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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
  `serviceType` varchar(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_name` (`nameIndex`),
  KEY `key_nametext` (`name`),
  KEY `key_servicetype` (`serviceType`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `almanac_servicename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_multimeter` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_multimeter`;

CREATE TABLE `multimeter_context` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameHash` binary(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`nameHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `multimeter_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventType` int(10) unsigned NOT NULL,
  `eventLabelID` int(10) unsigned NOT NULL,
  `resourceCost` bigint(20) NOT NULL,
  `sampleRate` int(10) unsigned NOT NULL,
  `eventContextID` int(10) unsigned NOT NULL,
  `eventHostID` int(10) unsigned NOT NULL,
  `eventViewerID` int(10) unsigned NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  `requestKey` binary(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_request` (`requestKey`),
  KEY `key_type` (`eventType`,`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `multimeter_host` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameHash` binary(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`nameHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `multimeter_label` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameHash` binary(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`nameHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `multimeter_viewer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `nameHash` binary(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`nameHash`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_spaces` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_spaces`;

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

CREATE TABLE `spaces_namespace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `namespaceName` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `isDefaultNamespace` tinyint(1) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `isArchived` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_default` (`isDefaultNamespace`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `spaces_namespacetransaction` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_phurl` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_phurl`;

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

CREATE TABLE `phurl_phurlname_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phurl_url` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `longURL` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `spacePHID` varbinary(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `alias` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} DEFAULT NULL,
  `authorPHID` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_instance` (`alias`),
  KEY `key_author` (`authorPHID`),
  KEY `key_space` (`spacePHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `phurl_urltransaction` (
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

CREATE TABLE `phurl_urltransaction_comment` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_badges` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_badges`;

CREATE TABLE `badges_award` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `badgePHID` varbinary(64) NOT NULL,
  `recipientPHID` varbinary(64) NOT NULL,
  `awarderPHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_badge` (`badgePHID`,`recipientPHID`),
  KEY `key_recipient` (`recipientPHID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `badges_badge` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(255) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `flavor` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `description` longtext COLLATE {$COLLATE_TEXT} NOT NULL,
  `icon` varchar(255) COLLATE {$COLLATE_TEXT} NOT NULL,
  `quality` int(10) unsigned NOT NULL,
  `status` varchar(32) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `creatorPHID` varbinary(64) NOT NULL,
  `mailKey` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  KEY `key_creator` (`creatorPHID`,`dateModified`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `badges_badgename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `badges_transaction` (
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

CREATE TABLE `badges_transaction_comment` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_packages` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_packages`;

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

CREATE TABLE `packages_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `publisherPHID` varbinary(64) NOT NULL,
  `packageKey` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `viewPolicy` varbinary(64) NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_package` (`publisherPHID`,`packageKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `packages_packagename_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `packages_packagetransaction` (
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

CREATE TABLE `packages_publisher` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `publisherKey` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `editPolicy` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_publisher` (`publisherKey`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `packages_publishername_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `packages_publishertransaction` (
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

CREATE TABLE `packages_version` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `name` varchar(64) CHARACTER SET {$CHARSET_SORT} COLLATE {$COLLATE_SORT} NOT NULL,
  `packagePHID` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`),
  UNIQUE KEY `key_package` (`packagePHID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `packages_versionname_ngrams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `objectID` int(10) unsigned NOT NULL,
  `ngram` char(3) COLLATE {$COLLATE_TEXT} NOT NULL,
  PRIMARY KEY (`id`),
  KEY `key_object` (`objectID`),
  KEY `key_ngram` (`ngram`,`objectID`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `packages_versiontransaction` (
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_application` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_application`;

CREATE TABLE `application_application` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varbinary(64) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};

CREATE TABLE `application_applicationtransaction` (
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
