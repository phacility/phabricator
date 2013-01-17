<?php

final class PhabricatorUserOAuthInfo extends PhabricatorUserDAO {

  const TOKEN_STATUS_NONE     = 'none';
  const TOKEN_STATUS_GOOD     = 'good';
  const TOKEN_STATUS_FAIL     = 'fail';
  const TOKEN_STATUS_EXPIRED  = 'xpyr';

  protected $userID;
  protected $oauthProvider;
  protected $oauthUID;

  protected $accountURI;
  protected $accountName;

  protected $token;
  protected $tokenExpires;
  protected $tokenScope;
  protected $tokenStatus;

  public function getTokenStatus() {
    if (!$this->token) {
      return self::TOKEN_STATUS_NONE;
    }

    if ($this->tokenExpires && $this->tokenExpires <= time()) {
      return self::TOKEN_STATUS_EXPIRED;
    }

    return $this->tokenStatus;
  }

  public static function getReadableTokenStatus($status) {
    static $map = array(
      self::TOKEN_STATUS_NONE     => 'No Token',
      self::TOKEN_STATUS_GOOD     => 'Token Good',
      self::TOKEN_STATUS_FAIL     => 'Token Failed',
      self::TOKEN_STATUS_EXPIRED  => 'Token Expired',
    );
    return idx($map, $status, 'Unknown');
  }

  public static function getRappableTokenStatus($status) {
    static $map = array(
      self::TOKEN_STATUS_NONE     => 'There is no token',
      self::TOKEN_STATUS_GOOD     => 'Your token is good',
      self::TOKEN_STATUS_FAIL     => 'Your token has failed',
      self::TOKEN_STATUS_EXPIRED  => 'Your token is old',
    );
    return idx($map, $status, 'This code\'s got bugs');
  }

}
