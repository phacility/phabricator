CREATE TABLE {$NAMESPACE}_feed.feed_storydata (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARCHAR(64) BINARY NOT NULL,
  UNIQUE KEY (phid),
  chronologicalKey BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY (chronologicalKey),
  storyType varchar(64) NOT NULL,
  storyData LONGBLOB NOT NULL,
  authorPHID varchar(64) BINARY NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
);

CREATE TABLE {$NAMESPACE}_feed.feed_storyreference (
  objectPHID varchar(64) BINARY NOT NULL,
  chronologicalKey BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY (objectPHID, chronologicalKey),
  KEY (chronologicalKey)
);
