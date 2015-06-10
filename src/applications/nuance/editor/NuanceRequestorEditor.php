<?php

final class NuanceRequestorEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Nuance Requestors');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = NuanceRequestorTransaction::TYPE_PROPERTY;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceRequestorTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue(
          NuanceRequestorTransaction::PROPERTY_KEY);
        return $object->getNuanceProperty($key);
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceRequestorTransaction::TYPE_PROPERTY:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceRequestorTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue(
          NuanceRequestorTransaction::PROPERTY_KEY);
        $object->setNuanceProperty($key, $xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceRequestorTransaction::TYPE_PROPERTY:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }
}
