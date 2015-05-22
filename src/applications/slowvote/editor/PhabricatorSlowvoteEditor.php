<?php

final class PhabricatorSlowvoteEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorSlowvoteApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Slowvotes');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;

    $types[] = PhabricatorSlowvoteTransaction::TYPE_QUESTION;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_RESPONSES;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_SHUFFLE;
    $types[] = PhabricatorSlowvoteTransaction::TYPE_CLOSE;

    return $types;
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
        if ($old === null) {
          return true;
        }
        return ((int)$old !== (int)$new);
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
        if ($old === null) {
          return true;
        }
        return ((bool)$old !== (bool)$new);
    }

    return parent::transactionHasEffect($object, $xaction);
  }


  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_QUESTION:
        return $object->getQuestion();
      case PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
        return $object->getResponseVisibility();
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
        return $object->getShuffle();
      case PhabricatorSlowvoteTransaction::TYPE_CLOSE:
        return $object->getIsClosed();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_QUESTION:
      case PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION:
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
      case PhabricatorSlowvoteTransaction::TYPE_CLOSE:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSlowvoteTransaction::TYPE_QUESTION:
        $object->setQuestion($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_RESPONSES:
        $object->setResponseVisibility($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_SHUFFLE:
        $object->setShuffle($xaction->getNewValue());
        break;
      case PhabricatorSlowvoteTransaction::TYPE_CLOSE:
        $object->setIsClosed((int)$xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

}
