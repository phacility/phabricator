ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthclientauthorization`
  ADD `scope` text NOT NULL;

ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserveraccesstoken`
  DROP `dateExpires`;
