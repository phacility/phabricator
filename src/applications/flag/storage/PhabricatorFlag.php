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

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'type' => 'text4',
        'color' => 'uint32',
        'note' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'ownerPHID' => array(
          'columns' => array('ownerPHID', 'type', 'objectPHID'),
          'unique' => true,
        ),
        'objectPHID' => array(
          'columns' => array('objectPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

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

  public function describeAutomaticCapability($capability) {
    return pht('Flags are private. Only you can view or edit your flags.');
  }

}
