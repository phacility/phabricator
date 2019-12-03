<?php

/**
 * Authentication adapter for Facebook OAuth2.
 */
final class PhutilFacebookAuthAdapter extends PhutilOAuthAuthAdapter {

  private $requireSecureBrowsing;

  public function setRequireSecureBrowsing($require_secure_browsing) {
    $this->requireSecureBrowsing = $require_secure_browsing;
    return $this;
  }

  public function getAdapterType() {
    return 'facebook';
  }

  public function getAdapterDomain() {
    return 'facebook.com';
  }

  public function getAccountID() {
    return $this->getOAuthAccountData('id');
  }

  public function getAccountEmail() {
    return $this->getOAuthAccountData('email');
  }

  public function getAccountName() {
    $link = $this->getOAuthAccountData('link');
    if (!$link) {
      return null;
    }

    $matches = null;
    if (!preg_match('@/([^/]+)$@', $link, $matches)) {
      return null;
    }

    return $matches[1];
  }

  public function getAccountImageURI() {
    $picture = $this->getOAuthAccountData('picture');
    if ($picture) {
      $picture_data = idx($picture, 'data');
      if ($picture_data) {
        return idx($picture_data, 'url');
      }
    }
    return null;
  }

  public function getAccountURI() {
    return $this->getOAuthAccountData('link');
  }

  public function getAccountRealName() {
    return $this->getOAuthAccountData('name');
  }

  public function getAccountSecuritySettings() {
    return $this->getOAuthAccountData('security_settings');
  }

  protected function getAuthenticateBaseURI() {
    return 'https://www.facebook.com/dialog/oauth';
  }

  protected function getTokenBaseURI() {
    return 'https://graph.facebook.com/oauth/access_token';
  }

  protected function loadOAuthAccountData() {
    $fields = array(
      'id',
      'name',
      'email',
      'link',
      'security_settings',
      'picture',
    );

    $uri = new PhutilURI('https://graph.facebook.com/me');
    $uri->replaceQueryParam('access_token', $this->getAccessToken());
    $uri->replaceQueryParam('fields', implode(',', $fields));
    list($body) = id(new HTTPSFuture($uri))->resolvex();

    $data = null;
    try {
      $data = phutil_json_decode($body);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Expected valid JSON response from Facebook account data request.'),
        $ex);
    }

    if ($this->requireSecureBrowsing) {
      if (empty($data['security_settings']['secure_browsing']['enabled'])) {
        throw new Exception(
          pht(
            'This Phabricator install requires you to enable Secure Browsing '.
            'on your Facebook account in order to use it to log in to '.
            'Phabricator. For more information, see %s',
            'https://www.facebook.com/help/156201551113407/'));
      }
    }

    return $data;
  }

}
