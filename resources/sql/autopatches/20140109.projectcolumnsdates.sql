ALTER TABLE {$NAMESPACE}_project.project_column
  ADD dateCreated INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_project.project_column
  ADD dateModified INT UNSIGNED NOT NULL;
