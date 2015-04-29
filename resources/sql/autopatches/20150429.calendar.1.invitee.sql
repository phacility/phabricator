CREATE TABLE {$NAMESPACE}_calendar.`calendar_eventinvitee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `eventPHID` varbinary(64) NOT NULL,
  `inviteePHID` varbinary(64) NOT NULL,
  `inviterPHID` varbinary(64) NOT NULL,
  `status` VARCHAR(64) COLLATE {$COLLATE_TEXT} NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  UNIQUE KEY `key_event` (`eventPHID`, `inviteePHID`),
  KEY `key_invitee` (`inviteePHID`)
) ENGINE=InnoDB COLLATE {$COLLATE_TEXT};
