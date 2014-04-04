CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildStepPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  logSource VARCHAR(255) NULL COLLATE utf8_bin,
  logType VARCHAR(255) NULL COLLATE utf8_bin,
  duration INT UNSIGNED NULL,
  live BOOLEAN NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_build` (buildPHID, buildStepPHID),
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_build
ADD COLUMN cancelRequested BOOLEAN NOT NULL;

CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlogchunk (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  logID INT UNSIGNED NOT NULL COLLATE utf8_bin,
  encoding VARCHAR(30) NOT NULL COLLATE utf8_bin,
  size LONG NULL,
  chunk LONGBLOB NOT NULL,
  KEY `key_log` (logID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
