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
    $types[] = PhabricatorProfilePanelConfigurationTransaction::TYPE_ORDER;
    $types[] = PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue('property.key');
        return $object->getPanelProperty($key, null);
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_ORDER:
        return $object->getPanelOrder();
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY:
        return $object->getVisibility();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY:
        return $xaction->getNewValue();
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_ORDER:
        return (int)$xaction->getNewValue();
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
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_ORDER:
        $object->setPanelOrder($xaction->getNewValue());
        return;
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY:
        $object->setVisibility($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY:
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_ORDER:
      case PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
