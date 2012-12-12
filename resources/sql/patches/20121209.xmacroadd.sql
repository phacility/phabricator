CREATE TABLE {$NAMESPACE}_file.macro_transaction (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  commentPHID VARCHAR(64) COLLATE utf8_bin,
  commentVersion INT UNSIGNED NOT NULL,
  transactionType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  oldValue LONGTEXT NOT NULL COLLATE utf8_bin,
  newValue LONGTEXT NOT NULL COLLATE utf8_bin,
  contentSource LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_object` (objectPHID)

) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_file.macro_transaction_comment (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  transactionPHID VARCHAR(64) COLLATE utf8_bin,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  commentVersion INT UNSIGNED NOT NULL,
  content LONGTEXT NOT NULL COLLATE utf8_bin,
  contentSource LONGTEXT NOT NULL COLLATE utf8_bin,
  isDeleted BOOL NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_version` (transactionPHID, commentVersion)

) ENGINE=InnoDB, COLLATE utf8_general_ci;

ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD dateCreated INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD dateModified INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD phid VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER id;

ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD isDisabled BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD KEY `key_disabled` (isDisabled);
