ALTER TABLE {$NAMESPACE}_oauth_server.oauth_server_oauthserverclient
  ADD isTrusted TINYINT(1) NOT NULL DEFAULT '0' AFTER creatorPHID;
