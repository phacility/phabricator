ALTER TABLE {$NAMESPACE}_repository.repository_symbol
  ADD symbolContext varchar(128) COLLATE utf8_general_ci NOT NULL DEFAULT ''
  AFTER arcanistProjectID;
