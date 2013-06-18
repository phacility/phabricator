TRUNCATE {$NAMESPACE}_user.user_externalaccount;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  CHANGE accountDomain accountDomain varchar(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  CHANGE displayName displayName varchar(255) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD username VARCHAR(255) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD realName VARCHAR(255) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD email VARCHAR(255) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD emailVerified BOOL NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD accountURI VARCHAR(255) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD profileImagePHID VARCHAR(64) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD properties LONGTEXT NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_user.user_externalaccount
  ADD KEY `key_userAccounts` (userPHID);
