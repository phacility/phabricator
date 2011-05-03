CREATE TABLE phabricator_herald.herald_savedheader (
  phid varchar(64) binary not null primary key,
  header varchar(255) not null
) ENGINE=InnoDB;