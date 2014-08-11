<?php

final class DrydockBlueprintCoreCustomField
  extends DrydockBlueprintCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'drydock:core';
  }

  public function createFields($object) {
    $impl = $object->getImplementation();
    $specs = $impl->getFieldSpecifications();

    return PhabricatorStandardCustomField::buildStandardFields($this, $specs);
  }

  public function shouldUseStorage() {
    return false;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $key = $this->getProxy()->getRawStandardFieldKey();
    $this->setValueFromStorage($object->getDetail($key));
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $object = $this->getObject();
    $key = $this->getProxy()->getRawStandardFieldKey();

    $this->setValueFromApplicationTransactions($xaction->getNewValue());
    $value = $this->getValueForStorage();

    $object->setDetail($key, $value);
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

}
