ALTER TABLE {$NAMESPACE}_oauth_server.oauth_server_oauthserverclient
  ADD viewPolicy VARBINARY(64) NOT NULL AFTER creatorPHID;

UPDATE {$NAMESPACE}_oauth_server.oauth_server_oauthserverclient
  SET viewPolicy = 'users' WHERE viewPolicy = '';

ALTER TABLE {$NAMESPACE}_oauth_server.oauth_server_oauthserverclient
  ADD editPolicy VARBINARY(64) NOT NULL AFTER viewPolicy;

UPDATE {$NAMESPACE}_oauth_server.oauth_server_oauthserverclient
  SET editPolicy = creatorPHID WHERE viewPolicy = '';
