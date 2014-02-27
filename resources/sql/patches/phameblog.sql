CREATE TABLE {$NAMESPACE}_phame.phame_blog (
  `id` INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `phid` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `name` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `description` LONGTEXT NOT NULL COLLATE utf8_bin,
  `configData` LONGTEXT NOT NULL COLLATE utf8_bin,
  `creatorPHID` VARCHAR(64) NOT NULL COLLATE utf8_bin,
  `dateCreated` INT UNSIGNED NOT NULL,
  `dateModified` INT UNSIGNED NOT NULL,
  UNIQUE KEY (`phid`)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_phame.edge (
  src VARCHAR(64) NOT NULL COLLATE utf8_bin,
  type VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dst VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  seq INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED,
  PRIMARY KEY (src, type, dst),
  KEY (src, type, dateCreated, seq)
) ENGINE=InnoDB, COLLATE utf8_general_ci;

CREATE TABLE {$NAMESPACE}_phame.edgedata (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  data LONGTEXT NOT NULL COLLATE utf8_bin
) ENGINE=InnoDB, COLLATE utf8_general_ci;

ALTER TABLE {$NAMESPACE}_phame.phame_post
  ADD KEY `instancePosts` (`visibility`, `datePublished`, `id`);
