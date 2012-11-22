CREATE TABLE {$NAMESPACE}_pholio.pholio_mock (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(128) NOT NULL COLLATE utf8_general_ci,
  originalName VARCHAR(128) NOT NULL COLLATE utf8_general_ci,
  description LONGTEXT NOT NULL COLLATE utf8_general_ci,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  coverPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  mailKey VARCHAR(20) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY (phid),
  KEY (authorPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_pholio.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_pholio.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_pholio.pholio_transaction (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  mockID INT UNSIGNED NOT NULL,
  transactionType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  oldValue LONGTEXT NOT NULL COLLATE utf8_bin,
  newValue LONGTEXT NOT NULL COLLATE utf8_bin,
  comment LONGTEXT NOT NULL COLLATE utf8_general_ci,
  metadata LONGTEXT NOT NULL COLLATE utf8_bin,
  contentSource LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_pholio.pholio_image (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  mockID INT UNSIGNED NOT NULL,
  filePHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(128) NOT NULL COLLATE utf8_general_ci,
  description LONGTEXT NOT NULL COLLATE utf8_general_ci,
  sequence INT UNSIGNED NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY (mockID, sequence)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_pholio.pholio_pixelcomment (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  mockID INT UNSIGNED NOT NULL,
  imageID INT UNSIGNED NOT NULL,
  transactionID INT UNSIGNED,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  x INT UNSIGNED NOT NULL,
  y INT UNSIGNED NOT NULL,
  width INT UNSIGNED NOT NULL,
  height INT UNSIGNED NOT NULL,
  comment LONGTEXT NOT NULL COLLATE utf8_general_ci,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY (mockID),
  KEY (authorPHID, transactionID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
