<?php

final class PhabricatorAuthSessionInfo extends Phobject {

  private $sessionType;
  private $identityPHID;
  private $isPartial;

  public function setSessionType($session_type) {
    $this->sessionType = $session_type;
    return $this;
  }

  public function getSessionType() {
    return $this->sessionType;
  }

  public function setIdentityPHID($identity_phid) {
    $this->identityPHID = $identity_phid;
    return $this;
  }

  public function getIdentityPHID() {
    return $this->identityPHID;
  }

  public function setIsPartial($is_partial) {
    $this->isPartial = $is_partial;
    return $this;
  }

  public function getIsPartial() {
    return $this->isPartial;
  }

}
