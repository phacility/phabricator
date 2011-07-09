ALTER TABLE phabricator_file.file
  ADD UNIQUE KEY (phid);

ALTER TABLE phabricator_project.project
  ADD UNIQUE KEY (phid);

ALTER TABLE phabricator_herald.herald_condition
  ADD KEY (ruleID);

ALTER TABLE phabricator_herald.herald_action
  ADD KEY (ruleID);