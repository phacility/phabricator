<?php

final class PhabricatorOAuthProviderPhabricator
extends PhabricatorOAuthProvider {
  private $userData;

  public function getExtraAuthParameters() {
    return array(
      'response_type' => 'code',
    );
  }

  public function getExtraTokenParameters() {
    return array(
      'grant_type' => 'authorization_code',
    );
  }

  public function decodeTokenResponse($response) {
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
      throw new Exception('Invalid token response.');
    }
    return $decoded;
  }

  public function getProviderKey() {
    return self::PROVIDER_PHABRICATOR;
  }

  public function getProviderName() {
    return 'Phabricator';
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('phabricator.auth-enabled');
  }

  public function isProviderLinkPermanent() {
    return PhabricatorEnv::getEnvConfig('phabricator.auth-permanent');
  }

  public function isProviderRegistrationEnabled() {
    return PhabricatorEnv::getEnvConfig('phabricator.registration-enabled');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('phabricator.application-id');
  }

  public function renderGetClientIDHelp() {
    return null;
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('phabricator.application-secret');
  }

  public function renderGetClientSecretHelp() {
    return null;
  }

  public function getAuthURI() {
    return $this->getURI('/oauthserver/auth/');
  }

  public function getTestURIs() {
    return array(
      $this->getURI('/'),
      $this->getURI('/api/user.whoami/')
    );
  }

  public function getTokenURI() {
    return $this->getURI('/oauthserver/token/');
  }

  protected function getTokenExpiryKey() {
    return 'expires_in';
  }

  public function getUserInfoURI() {
    return $this->getURI('/api/user.whoami');
  }

  public function getMinimumScope() {
    return 'whoami';
  }

  public function setUserData($data) {
    // legacy conditionally strip shield. see D3265 for discussion.
    if (strncmp($data, 'for(;;);', 8) === 0) {
      $data = substr($data, 8);
    }
    $data = idx(json_decode($data, true), 'result');
    $this->validateUserData($data);
    $this->userData = $data;
    return $this;
  }

  public function retrieveUserID() {
    return $this->userData['phid'];
  }

  public function retrieveUserEmail() {
    return $this->userData['email'];
  }

  public function retrieveUserAccountName() {
    return $this->userData['userName'];
  }

  public function retrieveUserProfileImage() {
    $uri = $this->userData['image'];
    return HTTPSFuture::loadContent($uri);
  }

  public function retrieveUserAccountURI() {
    return $this->userData['uri'];
  }

  public function retrieveUserRealName() {
    return $this->userData['realName'];
  }

  private function getURI($path) {
    return
      rtrim(PhabricatorEnv::getEnvConfig('phabricator.oauth-uri'), '/') .
      $path;
  }
}
