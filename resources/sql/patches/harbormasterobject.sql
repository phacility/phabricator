CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_object (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) NOT NULL COLLATE utf8_bin,
  name VARCHAR(255) COLLATE utf8_general_ci,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_harbormaster.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_harbormaster.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;
