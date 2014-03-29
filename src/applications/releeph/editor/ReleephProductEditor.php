<?php

final class ReleephProductEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = ReleephProductTransaction::TYPE_ACTIVE;

    return $types;
  }

  public function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ReleephProductTransaction::TYPE_ACTIVE:
        return (int)$object->getIsActive();
    }
  }

  public function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ReleephProductTransaction::TYPE_ACTIVE:
        return (int)$xaction->getNewValue();
    }
  }

  public function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case ReleephProductTransaction::TYPE_ACTIVE:
        $object->setIsActive($new);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    return;
  }

}
