CREATE TABLE {$NAMESPACE}_phragment.phragment_fragment (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  path VARCHAR(254) NOT NULL COLLATE utf8_bin,
  depth INT UNSIGNED NOT NULL,
  latestVersionPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid),
  UNIQUE KEY `key_path` (path)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_phragment.phragment_fragmentversion (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  sequence INT UNSIGNED NOT NULL,
  fragmentPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  filePHID VARCHAR(64) NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_version` (fragmentPHID, sequence)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
