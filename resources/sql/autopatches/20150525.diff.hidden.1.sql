CREATE TABLE {$NAMESPACE}_differential.differential_hiddencomment (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userPHID VARBINARY(64) NOT NULL,
  commentID INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_user` (userPHID, commentID),
  KEY `key_comment` (commentID)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
