CREATE TABLE IF NOT EXISTS {$NAMESPACE}_owners.owners_packagecommitrelationship (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packagePHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `commitPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `packagePHID` (`packagePHID`),
  KEY `commitPHID` (`commitPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
