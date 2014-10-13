<?php

final class PhortuneCartEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Carts');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhortuneCartTransaction::TYPE_CREATED;
    $types[] = PhortuneCartTransaction::TYPE_PURCHASED;
    $types[] = PhortuneCartTransaction::TYPE_HOLD;
    $types[] = PhortuneCartTransaction::TYPE_REVIEW;
    $types[] = PhortuneCartTransaction::TYPE_CANCEL;
    $types[] = PhortuneCartTransaction::TYPE_REFUND;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneCartTransaction::TYPE_CREATED:
      case PhortuneCartTransaction::TYPE_PURCHASED:
      case PhortuneCartTransaction::TYPE_HOLD:
      case PhortuneCartTransaction::TYPE_REVIEW:
      case PhortuneCartTransaction::TYPE_CANCEL:
      case PhortuneCartTransaction::TYPE_REFUND:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
