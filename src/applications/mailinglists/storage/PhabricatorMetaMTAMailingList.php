<?php

final class PhabricatorMetaMTAMailingList extends PhabricatorMetaMTADAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $email;
  protected $uri;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorMailingListListPHIDType::TYPECONST);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

}
