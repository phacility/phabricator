<?php

abstract class PhabricatorOAuthProvider {

  const PROVIDER_FACEBOOK    = 'facebook';
  const PROVIDER_GITHUB      = 'github';
  const PROVIDER_GOOGLE      = 'google';
  const PROVIDER_PHABRICATOR = 'phabricator';
  const PROVIDER_DISQUS      = 'disqus';

  private $accessToken;

  abstract public function getProviderKey();
  abstract public function getProviderName();
  abstract public function isProviderEnabled();
  abstract public function isProviderLinkPermanent();
  abstract public function isProviderRegistrationEnabled();
  abstract public function getClientID();
  abstract public function renderGetClientIDHelp();
  abstract public function getClientSecret();
  abstract public function renderGetClientSecretHelp();
  abstract public function getAuthURI();
  abstract public function getTestURIs();

  public function getSettingsPanelURI() {
    $panel = new PhabricatorSettingsPanelOAuth();
    $panel->setOAuthProvider($this);
    return $panel->getPanelURI();
  }

  /**
   * If the provider needs extra stuff in the auth request, return it here.
   * For example, Google needs a response_type parameter.
   */
  public function getExtraAuthParameters() {
    return array();
  }

  /**
   * If the provider supports application login, the diagnostics page can try
   * to test it. Most providers do not support this (Facebook does).
   */
  public function shouldDiagnoseAppLogin() {
    return false;
  }

  abstract public function getTokenURI();

  /**
   * Access tokens expire based on an implementation-specific key.
   */
  abstract protected function getTokenExpiryKey();
  public function getTokenExpiryFromArray(array $data) {
    $key = $this->getTokenExpiryKey();
    if ($key) {
      $expiry_value = idx($data, $key, 0);
      if ($expiry_value) {
        return time() + $expiry_value;
      }
    }
    return 0;
  }

  /**
   * If the provider needs extra stuff in the token request, return it here.
   * For example, Google needs a grant_type parameter.
   */
  public function getExtraTokenParameters() {
    return array();
  }

  abstract public function getUserInfoURI();
  abstract public function getMinimumScope();

  abstract public function setUserData($data);
  abstract public function retrieveUserID();
  abstract public function retrieveUserEmail();
  abstract public function retrieveUserAccountName();
  abstract public function retrieveUserProfileImage();
  abstract public function retrieveUserAccountURI();
  abstract public function retrieveUserRealName();

  /**
   * Override this if the provider returns the token response as, e.g., JSON
   * or XML.
   */
  public function decodeTokenResponse($response) {
    $data = null;
    parse_str($response, $data);
    return $data;
  }

  public function __construct() {

  }

  /**
   * This is where the OAuth provider will redirect the user after the user
   * grants Phabricator access.
   */
  final public function getRedirectURI() {
    $key = $this->getProviderKey();
    return PhabricatorEnv::getURI('/oauth/'.$key.'/login/');
  }

  final public function setAccessToken($access_token) {
    $this->accessToken = $access_token;
    return $this;
  }

  final public function getAccessToken() {
    return $this->accessToken;
  }

  /**
   * Often used within setUserData to make sure $data is not completely
   * junk. More granular validations of data might be necessary depending on
   * the provider and are generally encouraged.
   */
  final protected function validateUserData($data) {
    if (empty($data) || !is_array($data)) {
      throw new PhabricatorOAuthProviderException();
    }

    return true;
  }

  public static function newProvider($which) {
    switch ($which) {
      case self::PROVIDER_FACEBOOK:
        $class = 'PhabricatorOAuthProviderFacebook';
        break;
      case self::PROVIDER_GITHUB:
        $class = 'PhabricatorOAuthProviderGitHub';
        break;
      case self::PROVIDER_GOOGLE:
        $class = 'PhabricatorOAuthProviderGoogle';
        break;
      case self::PROVIDER_PHABRICATOR:
        $class = 'PhabricatorOAuthProviderPhabricator';
        break;
      case self::PROVIDER_DISQUS:
        $class = 'PhabricatorOAuthProviderDisqus';
        break;
      default:
        throw new Exception('Unknown OAuth provider.');
    }
    return newv($class, array());
  }

  public static function getAllProviders() {
    $all = array(
      self::PROVIDER_FACEBOOK,
      self::PROVIDER_GITHUB,
      self::PROVIDER_GOOGLE,
      self::PROVIDER_PHABRICATOR,
      self::PROVIDER_DISQUS,
    );
    $providers = array();
    foreach ($all as $provider) {
      $providers[$provider] = self::newProvider($provider);
    }
    return $providers;
  }

}
