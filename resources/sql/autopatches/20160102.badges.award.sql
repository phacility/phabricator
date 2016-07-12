CREATE TABLE {$NAMESPACE}_badges.badges_award (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  badgePHID VARBINARY(64) NOT NULL,
  recipientPHID VARBINARY(64) NOT NULL,
  awarderPHID varbinary(64) NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_badge` (badgePHID, recipientPHID),
  KEY `key_recipient` (recipientPHID)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
