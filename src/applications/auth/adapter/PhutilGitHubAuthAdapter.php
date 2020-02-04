<?php

/**
 * Authentication adapter for Github OAuth2.
 */
final class PhutilGitHubAuthAdapter extends PhutilOAuthAuthAdapter {

  public function getAdapterType() {
    return 'github';
  }

  public function getAdapterDomain() {
    return 'github.com';
  }

  public function getAccountID() {
    return $this->getOAuthAccountData('id');
  }

  public function getAccountEmail() {
    return $this->getOAuthAccountData('email');
  }

  public function getAccountName() {
    return $this->getOAuthAccountData('login');
  }

  public function getAccountImageURI() {
    return $this->getOAuthAccountData('avatar_url');
  }

  public function getAccountURI() {
    $name = $this->getAccountName();
    if (strlen($name)) {
      return 'https://github.com/'.$name;
    }
    return null;
  }

  public function getAccountRealName() {
    return $this->getOAuthAccountData('name');
  }

  protected function getAuthenticateBaseURI() {
    return 'https://github.com/login/oauth/authorize';
  }

  protected function getTokenBaseURI() {
    return 'https://github.com/login/oauth/access_token';
  }

  protected function loadOAuthAccountData() {
    $uri = new PhutilURI('https://api.github.com/user');

    $future = new HTTPSFuture($uri);

    // NOTE: GitHub requires a User-Agent string.
    $future->addHeader('User-Agent', __CLASS__);

    // See T13485. Circa early 2020, GitHub has deprecated use of the
    // "access_token" URI parameter.
    $token_header = sprintf('token %s', $this->getAccessToken());
    $future->addHeader('Authorization', $token_header);

    list($body) = $future->resolvex();

    try {
      return phutil_json_decode($body);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Expected valid JSON response from GitHub account data request.'),
        $ex);
    }
  }

}
