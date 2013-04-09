<?php

/**
 * An account represents a purchasing entity. An account may have multiple users
 * on it (e.g., several employees of a company have access to the company
 * account), and a user may have several accounts (e.g., a company account and
 * a personal account).
 */
final class PhortuneAccount extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  protected $name;
  protected $balanceInCents = 0;

  private $memberPHIDs;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_ACNT);
  }

  public function getMemberPHIDs() {
    if ($this->memberPHIDs === null) {
      throw new Exception("Call attachMemberPHIDs() before getMemberPHIDs()!");
    }
    return $this->memberPHIDs;
  }

  public function attachMemberPHIDs(array $phids) {
    $this->memberPHIDs = $phids;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return false;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $members = array_fuse($this->getMemberPHIDs());
    return isset($members[$viewer->getPHID()]);
  }

}
