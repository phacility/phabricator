CREATE TABLE {$NAMESPACE}_xhpastview.xhpastview_parsetree (
  id int unsigned not null auto_increment primary key,
  authorPHID varchar(64) binary,
  input longblob not null,
  stdout longblob not null,
  dateCreated int unsigned not null,
  dateModified int unsigned not null
);
