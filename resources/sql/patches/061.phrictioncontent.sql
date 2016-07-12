/* Patch 060 neglected to make this an AUTO_INCREMENT PRIMARY KEY */
ALTER TABLE {$NAMESPACE}_phriction.phriction_document
  CHANGE id id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY;

/* Needs to be initially nullable for insert when documents are created. */
ALTER TABLE {$NAMESPACE}_phriction.phriction_document
  CHANGE contentID contentID INT UNSIGNED;

CREATE TABLE {$NAMESPACE}_phriction.phriction_content (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  documentID INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  UNIQUE KEY (documentID, version),
  authorPHID VARCHAR(64) BINARY NOT NULL,
  KEY (authorPHID),
  title VARCHAR(512) NOT NULL,
  slug VARCHAR(512) NOT NULL,
  KEY (slug(128)),
  content LONGBLOB NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
