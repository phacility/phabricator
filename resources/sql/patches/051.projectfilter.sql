CREATE TABLE {$NAMESPACE}_maniphest.maniphest_taskproject (
  taskPHID varchar(64) BINARY NOT NULL,
  projectPHID varchar(64) BINARY NOT NULL,
  PRIMARY KEY (projectPHID, taskPHID),
  UNIQUE KEY (taskPHID, projectPHID)
);
