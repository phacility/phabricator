ALTER TABLE {$NAMESPACE}_project.project
  ADD parentProjectPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_project.project
  ADD hasWorkboard BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_project.project
  ADD hasMilestones BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_project.project
  ADD hasSubprojects BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_project.project
  ADD milestoneNumber INT UNSIGNED;

ALTER TABLE {$NAMESPACE}_project.project
  ADD UNIQUE KEY `key_milestone` (parentProjectPHID, milestoneNumber);
