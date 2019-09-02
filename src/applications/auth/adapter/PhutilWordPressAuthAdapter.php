<?php

/**
 * Authentication adapter for WordPress.com OAuth2.
 */
final class PhutilWordPressAuthAdapter extends PhutilOAuthAuthAdapter {

  public function getAdapterType() {
    return 'wordpress';
  }

  public function getAdapterDomain() {
    return 'wordpress.com';
  }

  public function getAccountID() {
    return $this->getOAuthAccountData('ID');
  }

  public function getAccountEmail() {
    return $this->getOAuthAccountData('email');
  }

  public function getAccountName() {
    return $this->getOAuthAccountData('username');
  }

  public function getAccountImageURI() {
    return $this->getOAuthAccountData('avatar_URL');
  }

  public function getAccountURI() {
    return $this->getOAuthAccountData('profile_URL');
  }

  public function getAccountRealName() {
    return $this->getOAuthAccountData('display_name');
  }

  protected function getAuthenticateBaseURI() {
    return 'https://public-api.wordpress.com/oauth2/authorize';
  }

  protected function getTokenBaseURI() {
    return 'https://public-api.wordpress.com/oauth2/token';
  }

  public function getScope() {
    return 'user_read';
  }

  public function getExtraAuthenticateParameters() {
    return array(
      'response_type' => 'code',
      'blog_id' => 0,
    );
  }

  public function getExtraTokenParameters() {
    return array(
      'grant_type' => 'authorization_code',
    );
  }

  protected function loadOAuthAccountData() {
    return id(new PhutilWordPressFuture())
      ->setClientID($this->getClientID())
      ->setAccessToken($this->getAccessToken())
      ->setRawWordPressQuery('/me/')
      ->resolve();
  }

}
