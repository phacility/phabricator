alter table {$NAMESPACE}_user.user_oauthinfo add accountURI varchar(255);
alter table {$NAMESPACE}_user.user_oauthinfo add accountName varchar(255);
alter table {$NAMESPACE}_user.user_oauthinfo add token varchar(255);
alter table {$NAMESPACE}_user.user_oauthinfo add tokenExpires int unsigned;
alter table {$NAMESPACE}_user.user_oauthinfo add tokenScope varchar(255);
alter table {$NAMESPACE}_user.user_oauthinfo add tokenStatus varchar(255);
