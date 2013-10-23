CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildable (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildablePHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  containerPHID VARCHAR(64) COLLATE utf8_bin,
  buildStatus VARCHAR(32) NOT NULL COLLATE utf8_bin,
  buildableStatus VARCHAR(32) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_buildable` (buildablePHID),
  KEY `key_container` (containerPHID),
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildartifact (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  buildablePHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  artifactType VARCHAR(32) NOT NULL COLLATE utf8_bin,
  artifactIndex VARCHAR(12) NOT NULL COLLATE utf8_bin,
  artifactKey VARCHAR(255) NOT NULL COLLATE utf8_bin,
  artifactData LONGTEXT NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_artifact` (buildablePHID, artifactType, artifactIndex),
  UNIQUE KEY `key_artifact_type` (artifactType, artifactIndex),
  KEY `key_garbagecollect` (artifactType, dateCreated)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildplan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) NOT NULL,
  planStatus VARCHAR(32) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_status` (planStatus)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildplantransaction (
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

CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_build (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildablePHID varchar(64) NOT NULL COLLATE utf8_bin,
  buildPlanPHID varchar(64) NOT NULL COLLATE utf8_bin,
  buildStatus VARCHAR(32) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  KEY `key_buildable` (buildablePHID),
  KEY `key_plan` (buildPlanPHID),
  KEY `key_status` (buildStatus)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
