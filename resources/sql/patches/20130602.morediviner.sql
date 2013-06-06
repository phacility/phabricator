ALTER TABLE {$NAMESPACE}_diviner.diviner_livebook
  ADD configurationData LONGTEXT COLLATE utf8_bin NOT NULL;

UPDATE {$NAMESPACE}_diviner.diviner_livebook
  SET configurationData = '{}' WHERE configurationData = '';

ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD title VARCHAR(255);

ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD groupName VARCHAR(255);

ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD summary LONGTEXT COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD isDocumentable BOOL NOT NULL;
