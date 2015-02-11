<?php

final class AlmanacCoreCustomField
  extends AlmanacCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'almanac:core';
  }

  public function getFieldKey() {
    return $this->getProxy()->getRawStandardFieldKey();
  }

  public function getFieldName() {
    return $this->getFieldKey();
  }

  public function createFields($object) {

    $specs = $object->getAlmanacPropertyFieldSpecifications();

    $default_specs = array();
    foreach ($object->getAlmanacProperties() as $property) {
      $default_specs[$property->getFieldName()] = array(
        'name' => $property->getFieldName(),
        'type' => 'text',
      );
    }

    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      $specs + $default_specs);
  }

  public function shouldUseStorage() {
    return false;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $key = $this->getFieldKey();

    if ($object->hasAlmanacProperty($key)) {
      $this->setValueFromStorage($object->getAlmanacPropertyValue($key));
    }
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {

    $object = $this->getObject();
    $phid = $object->getPHID();
    $key = $this->getFieldKey();

    $property = id(new AlmanacPropertyQuery())
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($phid))
      ->withNames(array($key))
      ->executeOne();
    if (!$property) {
      $property = id(new AlmanacProperty())
        ->setObjectPHID($phid)
        ->setFieldIndex(PhabricatorHash::digestForIndex($key))
        ->setFieldName($key);
    }

    $property
      ->setFieldValue($xaction->getNewValue())
      ->save();
  }

}
