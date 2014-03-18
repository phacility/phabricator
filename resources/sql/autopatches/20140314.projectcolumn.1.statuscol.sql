ALTER TABLE {$NAMESPACE}_project.project_column
  ADD COLUMN status INT UNSIGNED NOT NULL AFTER name;
