<?php

/**
 * Authentication adapter for Asana OAuth2.
 */
final class PhutilAsanaAuthAdapter extends PhutilOAuthAuthAdapter {

  public function getAdapterType() {
    return 'asana';
  }

  public function getAdapterDomain() {
    return 'asana.com';
  }

  public function getAccountID() {
    // See T13453. The Asana API has changed to string IDs and now returns a
    // "gid" field (previously, it returned an "id" field).
    return $this->getOAuthAccountData('gid');
  }

  public function getAccountEmail() {
    return $this->getOAuthAccountData('email');
  }

  public function getAccountName() {
    return null;
  }

  public function getAccountImageURI() {
    $photo = $this->getOAuthAccountData('photo', array());
    if (is_array($photo)) {
      return idx($photo, 'image_128x128');
    } else {
      return null;
    }
  }

  public function getAccountURI() {
    return null;
  }

  public function getAccountRealName() {
    return $this->getOAuthAccountData('name');
  }

  protected function getAuthenticateBaseURI() {
    return 'https://app.asana.com/-/oauth_authorize';
  }

  protected function getTokenBaseURI() {
    return 'https://app.asana.com/-/oauth_token';
  }

  public function getScope() {
    return null;
  }

  public function getExtraAuthenticateParameters() {
    return array(
      'response_type' => 'code',
    );
  }

  public function getExtraTokenParameters() {
    return array(
      'grant_type' => 'authorization_code',
    );
  }

  public function getExtraRefreshParameters() {
    return array(
      'grant_type' => 'refresh_token',
    );
  }

  public function supportsTokenRefresh() {
    return true;
  }

  protected function loadOAuthAccountData() {
    return id(new PhutilAsanaFuture())
      ->setAccessToken($this->getAccessToken())
      ->setRawAsanaQuery('users/me')
      ->resolve();
  }

}
