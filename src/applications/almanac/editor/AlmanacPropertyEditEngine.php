<?php

abstract class AlmanacPropertyEditEngine
  extends PhabricatorEditEngine {

  private $propertyKey;

  public function setPropertyKey($property_key) {
    $this->propertyKey = $property_key;
    return $this;
  }

  public function getPropertyKey() {
    return $this->propertyKey;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function isEngineExtensible() {
    return false;
  }

  public function getEngineName() {
    return pht('Almanac Properties');
  }

  public function getSummaryHeader() {
    return pht('Edit Almanac Property Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Almanac properties.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function newEditableObject() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Property');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Property');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Property: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Property');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Property');
  }

  protected function buildCustomEditFields($object) {
    $property_key = $this->getPropertyKey();
    $xaction_type = $object->getAlmanacPropertySetTransactionType();

    $specs = $object->getAlmanacPropertyFieldSpecifications();
    if (isset($specs[$property_key])) {
      $field_template = clone $specs[$property_key];
    } else {
      $field_template = new PhabricatorTextEditField();
    }

    return array(
      $field_template
        ->setKey('value')
        ->setMetadataValue('almanac.property', $property_key)
        ->setLabel($property_key)
        ->setTransactionType($xaction_type)
        ->setValue($object->getAlmanacPropertyValue($property_key)),
    );
  }

}
