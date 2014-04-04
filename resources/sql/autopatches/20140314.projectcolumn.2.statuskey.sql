ALTER TABLE {$NAMESPACE}_project.project_column
  ADD KEY `key_status` (`projectPHID`,`status`,`sequence`);
