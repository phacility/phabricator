ALTER TABLE {$NAMESPACE}_diviner.diviner_livebook
  ADD COLUMN repositoryPHID VARBINARY(64) AFTER name;

ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD COLUMN repositoryPHID VARBINARY(64) AFTER bookPHID;
