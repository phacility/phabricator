CREATE TABLE {$NAMESPACE}_repository.repository_symbol (
  arcanistProjectID INT UNSIGNED NOT NULL,
  symbolName varchar(128) NOT NULL,
  KEY (symbolName),
  symbolType varchar(12) BINARY NOT NULL,
  symbolLanguage varchar(32) BINARY NOT NULL,
  pathID INT UNSIGNED NOT NULL,
  lineNumber INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
