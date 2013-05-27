<?php

/**
 * @group search
 */
final class PhabricatorNamedQuery extends PhabricatorSearchDAO
  implements PhabricatorPolicyInterface {

  protected $queryKey = "";
  protected $queryName = "";
  protected $userPHID = "";
  protected $engineClassName = "";

  private $isBuiltin;

  public function setIsBuiltin($is_builtin) {
    $this->isBuiltin = $is_builtin;
    return $this;
  }

  public function getIsBuiltin() {
    return $this->isBuiltin;
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($viewer->getPHID() == $this->userPHID) {
      return true;
    }
    return false;
  }

}
