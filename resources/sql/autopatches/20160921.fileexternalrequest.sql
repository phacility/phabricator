CREATE TABLE {$NAMESPACE}_file.file_externalrequest (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  filePHID VARBINARY(64),
  ttl INT UNSIGNED NOT NULL,
  uri LONGTEXT NOT NULL,
  uriIndex BINARY(12) NOT NULL,
  isSuccessful BOOL NOT NULL,
  responseMessage LONGTEXT,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_uriindex` (uriIndex),
  KEY `key_ttl` (ttl),
  KEY `key_file` (filePHID)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
