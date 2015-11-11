CREATE TABLE {$NAMESPACE}_phame.phame_posttransaction_comment (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phid VARCHAR(64) NOT NULL,
  transactionPHID VARCHAR(64),
  authorPHID VARCHAR(64) NOT NULL,
  viewPolicy VARCHAR(64) NOT NULL,
  editPolicy VARCHAR(64) NOT NULL,
  commentVersion INT UNSIGNED NOT NULL,
  content LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  contentSource LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  isDeleted BOOL NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_version` (transactionPHID, commentVersion)

) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
