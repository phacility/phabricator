ALTER TABLE {$NAMESPACE}_file.file
  ADD UNIQUE KEY (phid);

ALTER TABLE {$NAMESPACE}_project.project
  ADD UNIQUE KEY (phid);

ALTER TABLE {$NAMESPACE}_herald.herald_condition
  ADD KEY (ruleID);

ALTER TABLE {$NAMESPACE}_herald.herald_action
  ADD KEY (ruleID);
