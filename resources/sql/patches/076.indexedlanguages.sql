ALTER TABLE {$NAMESPACE}_repository.repository_arcanistproject
  ADD symbolIndexLanguages LONGBLOB NOT NULL;
ALTER TABLE {$NAMESPACE}_repository.repository_arcanistproject
  ADD symbolIndexProjects LONGBLOB NOT NULL;
