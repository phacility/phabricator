<?php

final class AlmanacProperty
  extends PhabricatorCustomFieldStorage
  implements PhabricatorPolicyInterface {

  protected $fieldName;

  private $object = self::ATTACHABLE;

  public function getApplicationName() {
    return 'almanac';
  }

  public function getConfiguration() {
    $config = parent::getConfiguration();

    $config[self::CONFIG_COLUMN_SCHEMA] += array(
      'fieldName' => 'text128',
    );

    return $config;
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachObject(PhabricatorLiskDAO $object) {
    $this->object = $object;
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
    return $this->getObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getObject()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Properties inherit the policies of their object.');
  }

}
