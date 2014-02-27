CREATE TABLE {$NAMESPACE}_phriction.phriction_document (
  id INT UNSIGNED NOT NULL,
  phid VARCHAR(64) BINARY NOT NULL,
  UNIQUE KEY (phid),
  slug VARCHAR(128) NOT NULL,
  UNIQUE KEY (slug),
  depth INT UNSIGNED NOT NULL,
  UNIQUE KEY (depth, slug),
  contentID INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
