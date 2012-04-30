CREATE TABLE {$NAMESPACE}_herald.herald_ruleedit (
       id int unsigned not null auto_increment primary key,
       ruleID int unsigned not null,
       editorPHID varchar(64) BINARY not null,
       dateCreated int unsigned not null,
       dateModified int unsigned not null,
       KEY (ruleID, dateCreated)
) ENGINE=InnoDB;
