ALTER TABLE {$NAMESPACE}_repository.repository_symbol
  ADD repositoryPHID varbinary(64) NOT NULL AFTER arcanistProjectID;
