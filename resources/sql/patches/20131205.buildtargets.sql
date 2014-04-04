CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildtarget (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildStepPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  className VARCHAR(255) NOT NULL COLLATE utf8_bin,
  details LONGTEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  variables LONGTEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_build` (buildPHID, buildStepPHID),
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

TRUNCATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog;
TRUNCATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlogchunk;
TRUNCATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildartifact;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog
DROP COLUMN buildPHID;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog
DROP COLUMN buildStepPHID;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildartifact
DROP COLUMN buildablePHID;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog
ADD COLUMN buildTargetPHID VARCHAR(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildartifact
ADD COLUMN buildTargetPHID VARCHAR(64) NOT NULL COLLATE utf8_bin;
