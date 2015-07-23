<?php

final class ConduitAPIRequest extends Phobject {

  protected $params;
  private $user;
  private $isClusterRequest = false;

  public function __construct(array $params) {
    $this->params = $params;
  }

  public function getValue($key, $default = null) {
    return coalesce(idx($this->params, $key), $default);
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

  public function setIsClusterRequest($is_cluster_request) {
    $this->isClusterRequest = $is_cluster_request;
    return $this;
  }

  public function getIsClusterRequest() {
    return $this->isClusterRequest;
  }

}
