<?php

final class AlmanacProperty
  extends PhabricatorCustomFieldStorage
  implements PhabricatorPolicyInterface {

  protected $fieldName;

  private $object = self::ATTACHABLE;

  public function getApplicationName() {
    return 'almanac';
  }

  protected function getConfiguration() {
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

  public static function buildTransactions(
    AlmanacPropertyInterface $object,
    array $properties) {

    $template = $object->getApplicationTransactionTemplate();

    $attached_properties = $object->getAlmanacProperties();
    foreach ($properties as $key => $value) {
      if (empty($attached_properties[$key])) {
        $attached_properties[] = id(new AlmanacProperty())
          ->setObjectPHID($object->getPHID())
          ->setFieldName($key);
      }
    }
    $object->attachAlmanacProperties($attached_properties);

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_DEFAULT);
    $fields = $field_list->getFields();

    $xactions = array();
    foreach ($properties as $name => $property) {
      $xactions[] = id(clone $template)
        ->setTransactionType(PhabricatorTransactions::TYPE_CUSTOMFIELD)
        ->setMetadataValue('customfield:key', $name)
        ->setOldValue($object->getAlmanacPropertyValue($name))
        ->setNewValue($property);
    }

    return $xactions;
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
