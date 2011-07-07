CREATE TABLE phabricator_maniphest.maniphest_tasksubscriber (
  taskPHID varchar(64) BINARY NOT NULL,
  subscriberPHID varchar(64) BINARY NOT NULL,
  PRIMARY KEY (subscriberPHID, taskPHID),
  UNIQUE KEY (taskPHID, subscriberPHID)
);
