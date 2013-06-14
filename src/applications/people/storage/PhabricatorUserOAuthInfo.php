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

  public static function loadOneByUserAndProviderKey(
    PhabricatorUser $user,
    $provider_key) {

    return id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'userID = %d AND oauthProvider = %s',
      $user->getID(),
      $provider_key);
  }

  public static function loadAllOAuthProvidersByUser(
    PhabricatorUser $user) {

    return id(new PhabricatorUserOAuthInfo())->loadAllWhere(
      'userID = %d',
      $user->getID());
  }

  public static function loadOneByProviderKeyAndAccountID(
    $provider_key,
    $account_id) {

    return id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'oauthProvider = %s and oauthUID = %s',
      $provider_key,
      $account_id);
  }


}
