ALTER TABLE phabricator_repository.repository_arcanistproject
  ADD symbolIndexLanguages LONGBLOB NOT NULL;
ALTER TABLE phabricator_repository.repository_arcanistproject
  ADD symbolIndexProjects LONGBLOB NOT NULL;