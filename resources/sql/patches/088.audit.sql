ALTER TABLE {$NAMESPACE}_owners.owners_packagecommitrelationship
  ADD COLUMN `auditStatus` varchar(64) NOT NULL,
  ADD COLUMN `auditReasons` longtext NOT NULL,
  DROP KEY `packagePHID`,
  ADD KEY `packagePHID` (`packagePHID`, `auditStatus`, `id`);

CREATE TABLE IF NOT EXISTs {$NAMESPACE}_audit.audit_comment (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `targetPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `actorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `action` varchar(64) NOT NULL,
  `content` longtext NOT NULL,
  PRIMARY KEY `id` (`id`),
  KEY `targetPHID` (`targetPHID`, `actorPHID`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE {$NAMESPACE}_owners.owners_package
  ADD COLUMN `auditingEnabled` tinyint(1) NOT NULL DEFAULT 0;
