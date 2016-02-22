<?php

final class AlmanacProperty
  extends AlmanacDAO
  implements PhabricatorPolicyInterface {

  protected $objectPHID;
  protected $fieldIndex;
  protected $fieldName;
  protected $fieldValue;

  private $object = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'fieldValue' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'fieldIndex' => 'bytes12',
        'fieldName' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'objectPHID' => array(
          'columns' => array('objectPHID', 'fieldIndex'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachObject(PhabricatorLiskDAO $object) {
    $this->object = $object;
    return $this;
  }

  public function save() {
    $hash = PhabricatorHash::digestForIndex($this->getFieldName());
    $this->setFieldIndex($hash);

    return parent::save();
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
