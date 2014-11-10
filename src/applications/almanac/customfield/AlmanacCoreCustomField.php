<?php

final class AlmanacCoreCustomField
  extends AlmanacCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'almanac:core';
  }

  public function createFields($object) {
    $specs = array();

    foreach ($object->getAlmanacProperties() as $property) {
      $specs[$property->getFieldName()] = array(
        'name' => $property->getFieldName(),
        'type' => 'text',
      );
    }

    return PhabricatorStandardCustomField::buildStandardFields($this, $specs);
  }

  public function shouldUseStorage() {
    return false;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $key = $this->getProxy()->getRawStandardFieldKey();
    $this->setValueFromStorage($object->getAlmanacPropertyValue($key));
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {

    $object = $this->getObject();
    $phid = $object->getPHID();
    $key = $this->getProxy()->getRawStandardFieldKey();

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
