<?php

final class PhluxVariableEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhluxApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phlux Variables');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhluxTransaction::TYPE_EDIT_KEY;
    $types[] = PhluxTransaction::TYPE_EDIT_VALUE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhluxTransaction::TYPE_EDIT_KEY:
        return $object->getVariableKey();
      case PhluxTransaction::TYPE_EDIT_VALUE:
        return $object->getVariableValue();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhluxTransaction::TYPE_EDIT_KEY:
      case PhluxTransaction::TYPE_EDIT_VALUE:
        return $xaction->getNewValue();
    }
    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhluxTransaction::TYPE_EDIT_KEY:
        $object->setVariableKey($xaction->getNewValue());
        return;
      case PhluxTransaction::TYPE_EDIT_VALUE:
        $object->setVariableValue($xaction->getNewValue());
        return;
    }
    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhluxTransaction::TYPE_EDIT_KEY:
      case PhluxTransaction::TYPE_EDIT_VALUE:
        return;
    }
    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
