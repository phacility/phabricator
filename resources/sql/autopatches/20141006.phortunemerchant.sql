CREATE TABLE {$NAMESPACE}_phortune.phortune_merchant (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) NOT NULL COLLATE utf8_bin,
  viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_phid` (phid)
) ENGINE=InnoDB, COLLATE=utf8_bin;
