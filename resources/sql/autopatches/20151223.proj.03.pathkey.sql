ALTER TABLE {$NAMESPACE}_project.project
  ADD KEY `key_path` (projectPath, projectDepth);
