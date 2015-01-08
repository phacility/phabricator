<?php

final class ReleephProductEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorReleephApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Releeph Products');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = ReleephProductTransaction::TYPE_ACTIVE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ReleephProductTransaction::TYPE_ACTIVE:
        return (int)$object->getIsActive();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case ReleephProductTransaction::TYPE_ACTIVE:
        return (int)$xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
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
