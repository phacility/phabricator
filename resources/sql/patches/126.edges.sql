CREATE TABLE phabricator_maniphest.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_maniphest.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;



CREATE TABLE phabricator_repository.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_repository.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;



CREATE TABLE phabricator_differential.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_differential.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;



CREATE TABLE phabricator_file.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_file.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;



CREATE TABLE phabricator_user.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_user.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;



CREATE TABLE phabricator_project.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_project.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;



CREATE TABLE phabricator_metamta.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE phabricator_metamta.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;
