<?php

/**
 * Authentication adapter for Slack OAuth2.
 */
final class PhutilSlackAuthAdapter extends PhutilOAuthAuthAdapter {

  public function getAdapterType() {
    return 'Slack';
  }

  public function getAdapterDomain() {
    return 'slack.com';
  }

  public function getAccountID() {
    $user = $this->getOAuthAccountData('user');
    return idx($user, 'id');
  }

  public function getAccountEmail() {
    $user = $this->getOAuthAccountData('user');
    return idx($user, 'email');
  }

  public function getAccountImageURI() {
    $user = $this->getOAuthAccountData('user');
    return idx($user, 'image_512');
  }

  public function getAccountRealName() {
    $user = $this->getOAuthAccountData('user');
    return idx($user, 'name');
  }

  protected function getAuthenticateBaseURI() {
    return 'https://slack.com/oauth/authorize';
  }

  protected function getTokenBaseURI() {
    return 'https://slack.com/api/oauth.access';
  }

  public function getScope() {
    return 'identity.basic,identity.team,identity.avatar';
  }

  public function getExtraAuthenticateParameters() {
    return array(
      'response_type' => 'code',
    );
  }

  protected function loadOAuthAccountData() {
    return id(new PhutilSlackFuture())
      ->setAccessToken($this->getAccessToken())
      ->setRawSlackQuery('users.identity')
      ->resolve();
  }

}
