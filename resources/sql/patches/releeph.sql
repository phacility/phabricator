CREATE TABLE {$NAMESPACE}_releeph.`releeph_project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `trunkBranch` varchar(255) NOT NULL,
  `repositoryID` int(10) unsigned NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `arcanistProjectID` int(10) unsigned NOT NULL,
  `createdByUserPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `projectID` int(10) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectName` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {$NAMESPACE}_releeph.`releeph_branch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `basename` varchar(64) NOT NULL,
  `releephProjectID` int(10) unsigned NOT NULL,
  `createdByUserPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cutPointCommitIdentifier`
    varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `cutPointCommitPHID`
    varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
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

CREATE TABLE {$NAMESPACE}_releeph.`releeph_request` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `branchID` int(10) unsigned NOT NULL,
  `summary` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `requestUserPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `requestCommitIdentifier`
    varchar(40) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `requestCommitPHID`
    varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `requestCommitOrdinal` int(10) unsigned NOT NULL,
  `commitIdentifier`
    varchar(40) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `committedByUserPHID`
    varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `commitPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `pickStatus` tinyint(4) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `userIntents` longtext CHARACTER SET utf8 COLLATE utf8_bin,
  `inBranch` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `requestIdentifierBranch` (`requestCommitIdentifier`,`branchID`),
  KEY `branchID` (`branchID`,`requestCommitOrdinal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {$NAMESPACE}_releeph.`releeph_requestevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `releephRequestID` int(10) unsigned NOT NULL,
  `actorPHID` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `details` longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `type` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {$NAMESPACE}_releeph.`releeph_event` (
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
