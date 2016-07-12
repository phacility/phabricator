CREATE TABLE {$NAMESPACE}_user.user_authinvite (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  authorPHID VARBINARY(64) NOT NULL,
  emailAddress VARCHAR(128) NOT NULL COLLATE {$COLLATE_SORT},
  verificationHash BINARY(12) NOT NULL,
  acceptedByPHID VARBINARY(64),
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_address` (emailAddress),
  UNIQUE KEY `key_code` (verificationHash)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
