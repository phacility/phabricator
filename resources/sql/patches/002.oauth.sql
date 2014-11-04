create table {$NAMESPACE}_user.user_oauthinfo (
  id int unsigned not null auto_increment primary key,
  userID int unsigned not null,
  oauthProvider varchar(255) COLLATE `binary` not null,
  oauthUID varchar(255) COLLATE `binary` not null,
  unique key (userID, oauthProvider),
  unique key (oauthProvider, oauthUID),
  dateCreated int unsigned not null,
  dateModified int unsigned not null
);

insert into {$NAMESPACE}_user.user_oauthinfo
  (userID, oauthProvider, oauthUID, dateCreated, dateModified)
  SELECT id, 'facebook', facebookUID, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
    FROM {$NAMESPACE}_user.user
    WHERE facebookUID is not null;

alter table {$NAMESPACE}_user.user drop facebookUID;
