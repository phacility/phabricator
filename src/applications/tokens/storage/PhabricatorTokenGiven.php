<?php

final class PhabricatorTokenGiven extends PhabricatorTokenDAO
  implements PhabricatorPolicyInterface {

  protected $authorPHID;
  protected $objectPHID;
  protected $tokenPHID;

  private $object = self::ATTACHABLE;

  public function attachObject(PhabricatorTokenReceiverInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getObject()->getPolicy($capability);
      default:
        return PhabricatorPolicies::POLICY_NOONE;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getObject()->hasAutomaticCapability(
          $capability,
          $user);
      default:
        if ($user->getPHID() == $this->authorPHID) {
          return true;
        }
        return false;
    }
  }

}
