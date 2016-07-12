<?php

final class FundBackerEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorFundApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Fund Backing');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = FundBackerTransaction::TYPE_STATUS;
    $types[] = FundBackerTransaction::TYPE_REFUND;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case FundBackerTransaction::TYPE_STATUS:
        return $object->getStatus();
      case FundBackerTransaction::TYPE_REFUND:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundBackerTransaction::TYPE_STATUS:
      case FundBackerTransaction::TYPE_REFUND:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundBackerTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case FundBackerTransaction::TYPE_REFUND:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundBackerTransaction::TYPE_STATUS:
      case FundBackerTransaction::TYPE_REFUND:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
