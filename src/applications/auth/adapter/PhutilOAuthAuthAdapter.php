<?php

/**
 * Abstract adapter for OAuth2 providers.
 */
abstract class PhutilOAuthAuthAdapter extends PhutilAuthAdapter {

  private $clientID;
  private $clientSecret;
  private $redirectURI;
  private $scope;
  private $state;
  private $code;

  private $accessTokenData;
  private $oauthAccountData;

  abstract protected function getAuthenticateBaseURI();
  abstract protected function getTokenBaseURI();
  abstract protected function loadOAuthAccountData();

  public function getAuthenticateURI() {
    $params = array(
      'client_id' => $this->getClientID(),
      'scope' => $this->getScope(),
      'redirect_uri' => $this->getRedirectURI(),
      'state' => $this->getState(),
    ) + $this->getExtraAuthenticateParameters();

    $uri = new PhutilURI($this->getAuthenticateBaseURI(), $params);

    return phutil_string_cast($uri);
  }

  public function getAdapterType() {
    $this_class = get_class($this);
    $type_name = str_replace('PhutilAuthAdapterOAuth', '', $this_class);
    return strtolower($type_name);
  }

  public function setState($state) {
    $this->state = $state;
    return $this;
  }

  public function getState() {
    return $this->state;
  }

  public function setCode($code) {
    $this->code = $code;
    return $this;
  }

  public function getCode() {
    return $this->code;
  }

  public function setRedirectURI($redirect_uri) {
    $this->redirectURI = $redirect_uri;
    return $this;
  }

  public function getRedirectURI() {
    return $this->redirectURI;
  }

  public function getExtraAuthenticateParameters() {
    return array();
  }

  public function getExtraTokenParameters() {
    return array();
  }

  public function getExtraRefreshParameters() {
    return array();
  }

  public function setScope($scope) {
    $this->scope = $scope;
    return $this;
  }

  public function getScope() {
    return $this->scope;
  }

  public function setClientSecret(PhutilOpaqueEnvelope $client_secret) {
    $this->clientSecret = $client_secret;
    return $this;
  }

  public function getClientSecret() {
    return $this->clientSecret;
  }

  public function setClientID($client_id) {
    $this->clientID = $client_id;
    return $this;
  }

  public function getClientID() {
    return $this->clientID;
  }

  public function getAccessToken() {
    return $this->getAccessTokenData('access_token');
  }

  public function getAccessTokenExpires() {
    return $this->getAccessTokenData('expires_epoch');
  }

  public function getRefreshToken() {
    return $this->getAccessTokenData('refresh_token');
  }

  protected function getAccessTokenData($key, $default = null) {
    if ($this->accessTokenData === null) {
      $this->accessTokenData = $this->loadAccessTokenData();
    }

    return idx($this->accessTokenData, $key, $default);
  }

  public function supportsTokenRefresh() {
    return false;
  }

  public function refreshAccessToken($refresh_token) {
    $this->accessTokenData = $this->loadRefreshTokenData($refresh_token);
    return $this;
  }

  protected function loadRefreshTokenData($refresh_token) {
    $params = array(
      'refresh_token' => $refresh_token,
    ) + $this->getExtraRefreshParameters();

    // NOTE: Make sure we return the refresh_token so that subsequent
    // calls to getRefreshToken() return it; providers normally do not echo
    // it back for token refresh requests.

    return $this->makeTokenRequest($params) + array(
      'refresh_token' => $refresh_token,
    );
  }

  protected function loadAccessTokenData() {
    $code = $this->getCode();
    if (!$code) {
      throw new PhutilInvalidStateException('setCode');
    }

    $params = array(
      'code' => $this->getCode(),
    ) + $this->getExtraTokenParameters();

    return $this->makeTokenRequest($params);
  }

  private function makeTokenRequest(array $params) {
    $uri = $this->getTokenBaseURI();
    $query_data = array(
      'client_id'       => $this->getClientID(),
      'client_secret'   => $this->getClientSecret()->openEnvelope(),
      'redirect_uri'    => $this->getRedirectURI(),
    ) + $params;

    $future = new HTTPSFuture($uri, $query_data);
    $future->setMethod('POST');
    list($body) = $future->resolvex();

    $data = $this->readAccessTokenResponse($body);

    if (isset($data['expires_in'])) {
      $data['expires_epoch'] = $data['expires_in'];
    } else if (isset($data['expires'])) {
      $data['expires_epoch'] = $data['expires'];
    }

    // If we got some "expires" value back, interpret it as an epoch timestamp
    // if it's after the year 2010 and as a relative number of seconds
    // otherwise.
    if (isset($data['expires_epoch'])) {
      if ($data['expires_epoch'] < (60 * 60 * 24 * 365 * 40)) {
        $data['expires_epoch'] += time();
      }
    }

    if (isset($data['error'])) {
      throw new Exception(pht('Access token error: %s', $data['error']));
    }

    return $data;
  }

  protected function readAccessTokenResponse($body) {
    // NOTE: Most providers either return JSON or HTTP query strings, so try
    // both mechanisms. If your provider does something else, override this
    // method.

    $data = json_decode($body, true);

    if (!is_array($data)) {
      $data = array();
      parse_str($body, $data);
    }

    if (empty($data['access_token']) &&
        empty($data['error'])) {
      throw new Exception(
        pht('Failed to decode OAuth access token response: %s', $body));
    }

    return $data;
  }

  protected function getOAuthAccountData($key, $default = null) {
    if ($this->oauthAccountData === null) {
      $this->oauthAccountData = $this->loadOAuthAccountData();
    }

    return idx($this->oauthAccountData, $key, $default);
  }

}
