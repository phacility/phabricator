<?php

final class PhabricatorUserOAuthInfo extends PhabricatorUserDAO {

  protected $userID;
  protected $oauthProvider;
  protected $oauthUID;

  protected $accountURI;
  protected $accountName;

  protected $token;
  protected $tokenExpires = 0;
  protected $tokenScope   = '';
  protected $tokenStatus  = 'unused';

}
