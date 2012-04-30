CREATE TABLE {$NAMESPACE}_audit.audit_inlinecomment (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  authorPHID varchar(64) COLLATE utf8_bin NOT NULL,
  commitPHID varchar(64) COLLATE utf8_bin NOT NULL,
  pathID INT UNSIGNED NOT NULL,
  auditCommentID INT UNSIGNED,
  isNewFile BOOL NOT NULL,
  lineNumber INT UNSIGNED NOT NULL,
  lineLength INT UNSIGNED NOT NULL,
  content LONGTEXT COLLATE utf8_bin,
  cache LONGTEXT COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY (commitPHID, pathID),
  KEY (authorPHID, commitPHID, auditCommentID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
