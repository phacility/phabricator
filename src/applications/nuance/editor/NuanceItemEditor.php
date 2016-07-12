<?php

final class NuanceItemEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Nuance Items');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = NuanceItemTransaction::TYPE_OWNER;
    $types[] = NuanceItemTransaction::TYPE_SOURCE;
    $types[] = NuanceItemTransaction::TYPE_REQUESTOR;
    $types[] = NuanceItemTransaction::TYPE_PROPERTY;
    $types[] = NuanceItemTransaction::TYPE_QUEUE;
    $types[] = NuanceItemTransaction::TYPE_COMMAND;

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceItemTransaction::TYPE_REQUESTOR:
        return $object->getRequestorPHID();
      case NuanceItemTransaction::TYPE_SOURCE:
        return $object->getSourcePHID();
      case NuanceItemTransaction::TYPE_OWNER:
        return $object->getOwnerPHID();
      case NuanceItemTransaction::TYPE_QUEUE:
        return $object->getQueuePHID();
      case NuanceItemTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue(
          NuanceItemTransaction::PROPERTY_KEY);
        return $object->getNuanceProperty($key);
      case NuanceItemTransaction::TYPE_COMMAND:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceItemTransaction::TYPE_REQUESTOR:
      case NuanceItemTransaction::TYPE_SOURCE:
      case NuanceItemTransaction::TYPE_OWNER:
      case NuanceItemTransaction::TYPE_PROPERTY:
      case NuanceItemTransaction::TYPE_QUEUE:
      case NuanceItemTransaction::TYPE_COMMAND:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceItemTransaction::TYPE_REQUESTOR:
        $object->setRequestorPHID($xaction->getNewValue());
        break;
      case NuanceItemTransaction::TYPE_SOURCE:
        $object->setSourcePHID($xaction->getNewValue());
        break;
      case NuanceItemTransaction::TYPE_OWNER:
        $object->setOwnerPHID($xaction->getNewValue());
        break;
      case NuanceItemTransaction::TYPE_QUEUE:
        $object->setQueuePHID($xaction->getNewValue());
        break;
      case NuanceItemTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue(
          NuanceItemTransaction::PROPERTY_KEY);
        $object->setNuanceProperty($key, $xaction->getNewValue());
        break;
      case NuanceItemTransaction::TYPE_COMMAND:
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceItemTransaction::TYPE_REQUESTOR:
      case NuanceItemTransaction::TYPE_SOURCE:
      case NuanceItemTransaction::TYPE_OWNER:
      case NuanceItemTransaction::TYPE_PROPERTY:
      case NuanceItemTransaction::TYPE_QUEUE:
      case NuanceItemTransaction::TYPE_COMMAND:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
