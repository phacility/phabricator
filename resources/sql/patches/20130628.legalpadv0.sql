CREATE TABLE {$NAMESPACE}_legalpad.legalpad_document (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  creatorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  versions INT UNSIGNED NOT NULL DEFAULT 0,
  documentBodyPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_creator` (creatorPHID, dateModified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {$NAMESPACE}_legalpad.legalpad_documentbody (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  creatorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  documentPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  version INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(255) NOT NULL COLLATE utf8_general_ci,
  text LONGTEXT NULL COLLATE utf8_general_ci,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_document` (documentPHID, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  documentPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  documentVersion INT UNSIGNED NOT NULL DEFAULT 0,
  signerPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_document` (documentPHID, documentVersion, signerPHID),
  KEY `key_signer` (signerPHID, dateModified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {$NAMESPACE}_legalpad.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_legalpad.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_legalpad.legalpad_transaction (
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
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  UNIQUE KEY `key_phid` (phid),
  KEY `key_object` (objectPHID)

) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_legalpad.legalpad_transaction_comment (
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

  documentID INT UNSIGNED,
  lineNumber INT UNSIGNED NOT NULL,
  lineLength INT UNSIGNED NOT NULL,
  fixedState VARCHAR(12) COLLATE utf8_bin,
  hasReplies BOOL NOT NULL,
  replyToCommentPHID VARCHAR(64),

  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_version` (transactionPHID, commentVersion),
  UNIQUE KEY `key_draft` (authorPHID, documentID, transactionPHID)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
