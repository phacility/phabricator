CREATE TABLE {$NAMESPACE}_phragment.phragment_snapshot (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  primaryFragmentPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(192) NOT NULL COLLATE utf8_bin,
  description LONGTEXT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_name` (primaryFragmentPHID, name)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_phragment.phragment_snapshotchild (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  snapshotPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  fragmentPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  fragmentVersionPHID VARCHAR(64) NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_child` (snapshotPHID, fragmentPHID, fragmentVersionPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
