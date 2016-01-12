<?php

final class PhabricatorProfilePanelEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Profile Panels');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue('property.key');
        return $object->getPanelProperty($key, null);
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue('property.key');
        $value = $xaction->getNewValue();
        $object->setPanelProperty($key, $value);
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
