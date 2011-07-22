ALTER TABLE phabricator_project.project
  ADD subprojectPHIDs longblob NOT NULL;
UPDATE phabricator_project.project
  SET subprojectPHIDs = '[]';

CREATE TABLE phabricator_project.project_subproject (
  projectPHID varchar(64) BINARY NOT NULL,
  subprojectPHID varchar(64) BINARY NOT NULL,
  PRIMARY KEY (subprojectPHID, projectPHID),
  UNIQUE KEY (projectPHID, subprojectPHID)
) ENGINE=InnoDB;