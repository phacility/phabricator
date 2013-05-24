<?php

final class PhabricatorRepositoryEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorRepositoryTransaction::TYPE_NAME;
    $types[] = PhabricatorRepositoryTransaction::TYPE_DESCRIPTION;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        return $object->getDetail('description');
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_NAME:
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        $object->setDetail('description', $xaction->getNewValue());
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

    }

    return parent::transactionHasEffect($object, $xaction);
  }

}
