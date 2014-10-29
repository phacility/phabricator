CREATE TABLE {$NAMESPACE}_herald.herald_action (
  id int unsigned not null auto_increment primary key,
  ruleID int unsigned not null,
  action varchar(255) not null,
  target text not null
);

CREATE TABLE {$NAMESPACE}_herald.herald_rule (
  id int unsigned not null auto_increment primary key,
  name varchar(255) COLLATE `binary` not null,
  authorPHID varchar(64) binary not null,
  contentType varchar(255) not null,
  mustMatchAll bool not null,
  configVersion int unsigned not null default '1',
  dateCreated int unsigned not null,
  dateModified int unsigned not null,
  unique key (authorPHID, name)
);

CREATE TABLE {$NAMESPACE}_herald.herald_condition (
  id int unsigned not null auto_increment primary key,
  ruleID int unsigned not null,
  fieldName varchar(255) not null,
  fieldCondition varchar(255) not null,
  value text not null
);

CREATE TABLE {$NAMESPACE}_herald.herald_transcript (
  id int unsigned not null auto_increment primary key,
  phid varchar(64) binary not null,
  time int unsigned not null,
  host varchar(255) not null,
  psth varchar(255) not null,
  duration float not null,
  objectPHID varchar(64) binary not null,
  dryRun bool not null,
  objectTranscript longblob not null,
  ruleTranscripts longblob not null,
  conditionTranscripts longblob not null,
  applyTranscripts longblob not null,
  unique key (phid)
);
