<?php

final class PhabricatorFlag extends PhabricatorFlagDAO
  implements PhabricatorPolicyInterface {

  protected $ownerPHID;
  protected $type;
  protected $objectPHID;
  protected $reasonPHID;
  protected $color = PhabricatorFlagColor::COLOR_BLUE;
  protected $note;

  private $handle = self::ATTACHABLE;
  private $object = self::ATTACHABLE;

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getHandle() {
    return $this->assertAttached($this->handle);
  }

  public function attachHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
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
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getOwnerPHID());
  }

}
