CREATE TABLE {$NAMESPACE}_project.project_columnposition (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  boardPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  columnPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  sequence INT UNSIGNED NOT NULL,
  UNIQUE KEY (boardPHID, columnPHID, objectPHID),
  KEY (objectPHID, boardPHID),
  KEY (boardPHID, columnPHID, sequence)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
