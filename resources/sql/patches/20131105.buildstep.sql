CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildstep (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildPlanPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  className VARCHAR(255) NOT NULL COLLATE utf8_bin,
  details LONGTEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_plan` (buildPlanPHID),
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
