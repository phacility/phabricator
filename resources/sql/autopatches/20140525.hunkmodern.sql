CREATE TABLE {$NAMESPACE}_differential.differential_hunk_modern (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  changesetID INT UNSIGNED NOT NULL,
  oldOffset INT UNSIGNED NOT NULL,
  oldLen INT UNSIGNED NOT NULL,
  newOffset INT UNSIGNED NOT NULL,
  newLen INT UNSIGNED NOT NULL,
  dataType CHAR(4) NOT NULL COLLATE latin1_bin,
  dataEncoding VARCHAR(16) COLLATE latin1_bin,
  dataFormat CHAR(4) NOT NULL COLLATE latin1_bin,
  data LONGBLOB NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,

  KEY `key_changeset` (changesetID),
  KEY `key_created` (dateCreated)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
