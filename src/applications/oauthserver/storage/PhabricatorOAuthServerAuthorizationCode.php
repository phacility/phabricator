<?php

final class PhabricatorOAuthServerAuthorizationCode
  extends PhabricatorOAuthServerDAO {

  protected $id;
  protected $code;
  protected $clientPHID;
  protected $clientSecret;
  protected $userPHID;
  protected $redirectURI;
}
