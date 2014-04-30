<?php

final class PhabricatorDashboardPanelCoreCustomField
  extends PhabricatorDashboardPanelCustomField
  implements PhabricatorStandardCustomFieldInterface {

  public function getStandardCustomFieldNamespace() {
    return 'dashboard:core';
  }

  public function createFields($object) {
    $impl = $object->requireImplementation();
    $specs = $impl->getFieldSpecifications();
    return PhabricatorStandardCustomField::buildStandardFields($this, $specs);
  }

  public function shouldUseStorage() {
    return false;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $key = $this->getProxy()->getRawStandardFieldKey();
    $this->setValueFromStorage($object->getProperty($key));
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $object = $this->getObject();
    $key = $this->getProxy()->getRawStandardFieldKey();

    $this->setValueFromApplicationTransactions($xaction->getNewValue());
    $value = $this->getValueForStorage();

    $object->setProperty($key, $value);
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

}
