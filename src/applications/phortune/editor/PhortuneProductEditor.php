<?php


final class PhortuneProductEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhortuneProductTransaction::TYPE_NAME;
    $types[] = PhortuneProductTransaction::TYPE_TYPE;
    $types[] = PhortuneProductTransaction::TYPE_PRICE;

    return $types;
  }


  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneProductTransaction::TYPE_NAME:
        return $object->getProductName();
      case PhortuneProductTransaction::TYPE_TYPE:
        return $object->getProductType();
      case PhortuneProductTransaction::TYPE_PRICE:
        return $object->getPriceInCents();
    }
    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneProductTransaction::TYPE_NAME:
      case PhortuneProductTransaction::TYPE_TYPE:
      case PhortuneProductTransaction::TYPE_PRICE:
        return $xaction->getNewValue();
    }
    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneProductTransaction::TYPE_NAME:
        $object->setProductName($xaction->getNewValue());
        return;
      case PhortuneProductTransaction::TYPE_TYPE:
        $object->setProductType($xaction->getNewValue());
        return;
      case PhortuneProductTransaction::TYPE_PRICE:
        $object->setPriceInCents($xaction->getNewValue());
        return;
    }
    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneProductTransaction::TYPE_NAME:
      case PhortuneProductTransaction::TYPE_TYPE:
      case PhortuneProductTransaction::TYPE_PRICE:
        return;
    }
    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
