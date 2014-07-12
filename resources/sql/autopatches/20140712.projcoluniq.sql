ALTER TABLE {$NAMESPACE}_project.project_column
  DROP KEY key_sequence;

ALTER TABLE {$NAMESPACE}_project.project_column
  ADD KEY key_sequence (projectPHID, sequence);
