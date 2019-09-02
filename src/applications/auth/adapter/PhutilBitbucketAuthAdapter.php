<?php

final class PhutilBitbucketAuthAdapter extends PhutilOAuth1AuthAdapter {

  private $userInfo;

  public function getAccountID() {
    return idx($this->getUserInfo(), 'username');
  }

  public function getAccountName() {
    return idx($this->getUserInfo(), 'display_name');
  }

  public function getAccountURI() {
    $name = $this->getAccountID();
    if (strlen($name)) {
      return 'https://bitbucket.org/'.$name;
    }
    return null;
  }

  public function getAccountImageURI() {
    return idx($this->getUserInfo(), 'avatar');
  }

  public function getAccountRealName() {
    $parts = array(
      idx($this->getUserInfo(), 'first_name'),
      idx($this->getUserInfo(), 'last_name'),
    );
    $parts = array_filter($parts);
    return implode(' ', $parts);
  }

  public function getAdapterType() {
    return 'bitbucket';
  }

  public function getAdapterDomain() {
    return 'bitbucket.org';
  }

  protected function getRequestTokenURI() {
    return 'https://bitbucket.org/api/1.0/oauth/request_token';
  }

  protected function getAuthorizeTokenURI() {
    return 'https://bitbucket.org/api/1.0/oauth/authenticate';
  }

  protected function getValidateTokenURI() {
    return 'https://bitbucket.org/api/1.0/oauth/access_token';
  }

  private function getUserInfo() {
    if ($this->userInfo === null) {
      // We don't need any of the data in the handshake, but do need to
      // finish the process. This makes sure we've completed the handshake.
      $this->getHandshakeData();

      $uri = new PhutilURI('https://bitbucket.org/api/1.0/user');

      $data = $this->newOAuth1Future($uri)
        ->setMethod('GET')
        ->resolveJSON();

      $this->userInfo = idx($data, 'user', array());
    }
    return $this->userInfo;
  }

}
