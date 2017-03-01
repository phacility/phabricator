<?php

final class ConduitAPIRequest extends Phobject {

  protected $params;
  private $user;
  private $isClusterRequest = false;
  private $oauthToken;
  private $isStrictlyTyped = true;

  public function __construct(array $params, $strictly_typed) {
    $this->params = $params;
    $this->isStrictlyTyped = $strictly_typed;
  }

  public function getValue($key, $default = null) {
    return coalesce(idx($this->params, $key), $default);
  }

  public function getValueExists($key) {
    return array_key_exists($key, $this->params);
  }

  public function getAllParameters() {
    return $this->params;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  /**
   * Retrieve the authentic identity of the user making the request. If a
   * method requires authentication (the default) the user object will always
   * be available. If a method does not require authentication (i.e., overrides
   * shouldRequireAuthentication() to return false) the user object will NEVER
   * be available.
   *
   * @return PhabricatorUser Authentic user, available ONLY if the method
   *                         requires authentication.
   */
  public function getUser() {
    if (!$this->user) {
      throw new Exception(
        pht(
          'You can not access the user inside the implementation of a Conduit '.
          'method which does not require authentication (as per %s).',
          'shouldRequireAuthentication()'));
    }
    return $this->user;
  }

  public function setOAuthToken(
    PhabricatorOAuthServerAccessToken $oauth_token) {
    $this->oauthToken = $oauth_token;
    return $this;
  }

  public function getOAuthToken() {
    return $this->oauthToken;
  }

  public function setIsClusterRequest($is_cluster_request) {
    $this->isClusterRequest = $is_cluster_request;
    return $this;
  }

  public function getIsClusterRequest() {
    return $this->isClusterRequest;
  }

  public function getIsStrictlyTyped() {
    return $this->isStrictlyTyped;
  }

  public function newContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorConduitContentSource::SOURCECONST);
  }

}
