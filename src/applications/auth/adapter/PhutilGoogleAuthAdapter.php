<?php

/**
 * Authentication adapter for Google OAuth2.
 */
final class PhutilGoogleAuthAdapter extends PhutilOAuthAuthAdapter {

  public function getAdapterType() {
    return 'google';
  }

  public function getAdapterDomain() {
    return 'google.com';
  }

  public function getAccountID() {
    return $this->getAccountEmail();
  }

  public function getAccountEmail() {
    return $this->getOAuthAccountData('email');
  }

  public function getAccountName() {
    // Guess account name from email address, this is just a hint anyway.
    $email = $this->getAccountEmail();
    $email = explode('@', $email);
    $email = head($email);
    return $email;
  }

  public function getAccountImageURI() {
    $uri = $this->getOAuthAccountData('picture');

    // Change the "sz" parameter ("size") from the default to 100 to ask for
    // a 100x100px image.
    if ($uri !== null) {
      $uri = new PhutilURI($uri);
      $uri->replaceQueryParam('sz', 100);
      $uri = (string)$uri;
    }

    return $uri;
  }

  public function getAccountURI() {
    return $this->getOAuthAccountData('link');
  }

  public function getAccountRealName() {
    return $this->getOAuthAccountData('name');
  }

  protected function getAuthenticateBaseURI() {
    return 'https://accounts.google.com/o/oauth2/auth';
  }

  protected function getTokenBaseURI() {
    return 'https://accounts.google.com/o/oauth2/token';
  }

  public function getScope() {
    $scopes = array(
      'email',
      'profile',
    );

    return implode(' ', $scopes);
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

  protected function loadOAuthAccountData() {
    $uri = new PhutilURI('https://www.googleapis.com/userinfo/v2/me');
    $uri->replaceQueryParam('access_token', $this->getAccessToken());

    $future = new HTTPSFuture($uri);
    list($status, $body) = $future->resolve();

    if ($status->isError()) {
      throw $status;
    }

    try {
      $result =  phutil_json_decode($body);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Expected valid JSON response from Google account data request.'),
        $ex);
    }

    return $result;
  }

}
