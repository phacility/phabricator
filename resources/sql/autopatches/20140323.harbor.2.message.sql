CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildmessage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  buildTargetPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(16) NOT NULL,
  isConsumed BOOL NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_buildtarget` (buildTargetPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
