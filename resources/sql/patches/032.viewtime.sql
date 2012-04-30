CREATE TABLE {$NAMESPACE}_differential.differential_viewtime (
  viewerPHID varchar(64) not null,
  objectPHID varchar(64) not null,
  viewTime int unsigned not null,
  PRIMARY KEY (viewerPHID, objectPHID)
);
