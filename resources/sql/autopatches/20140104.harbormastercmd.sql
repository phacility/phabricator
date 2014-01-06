CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_buildcommand (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  authorPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  targetPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  command VARCHAR(128) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  KEY `key_target` (targetPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_build
  DROP cancelRequested;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildtarget
  ADD targetStatus VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_harbormaster.harbormaster_buildtarget
  SET targetStatus = 'target/pending' WHERE targetStatus = '';
