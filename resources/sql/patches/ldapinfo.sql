CREATE TABLE {$NAMESPACE}_user.user_ldapinfo (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userID` int(10) unsigned NOT NULL,
  `ldapUsername` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
