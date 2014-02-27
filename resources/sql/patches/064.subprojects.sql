ALTER TABLE {$NAMESPACE}_project.project
  ADD subprojectPHIDs longblob NOT NULL;
UPDATE {$NAMESPACE}_project.project
  SET subprojectPHIDs = '[]';

CREATE TABLE {$NAMESPACE}_project.project_subproject (
  projectPHID varchar(64) BINARY NOT NULL,
  subprojectPHID varchar(64) BINARY NOT NULL,
  PRIMARY KEY (subprojectPHID, projectPHID),
  UNIQUE KEY (projectPHID, subprojectPHID)
) ENGINE=InnoDB;
