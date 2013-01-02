<?php

final class PhabricatorConfigEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorConfigTransaction::TYPE_EDIT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        return array(
          'deleted' => (bool)$object->getIsDeleted(),
          'value'   => $object->getValue(),
        );
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        $v = $xaction->getNewValue();

        $object->setIsDeleted($v['deleted']);
        $object->setValue($v['value']);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        // If an edit deletes an already-deleted entry, no-op it.
        if (idx($old, 'deleted') && idx($new, 'deleted')) {
          return false;
        }
        break;
    }

    return parent::transactionHasEffect($object, $xaction);
  }

}
