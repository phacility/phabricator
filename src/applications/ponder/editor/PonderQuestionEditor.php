<?php

final class PonderQuestionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PonderQuestionTransaction::TYPE_TITLE;
    $types[] = PonderQuestionTransaction::TYPE_CONTENT;

    return $types;
  }
  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
        return $object->getTitle();
      case PonderQuestionTransaction::TYPE_CONTENT:
        return $object->getContent();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
      case PonderQuestionTransaction::TYPE_CONTENT:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PonderQuestionTransaction::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        break;
      case PonderQuestionTransaction::TYPE_CONTENT:
        $object->setContent($xaction->getNewValue());
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
      case PonderQuestionTransaction::TYPE_TITLE:
      case PonderQuestionTransaction::TYPE_CONTENT:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  // TODO: Feed support
  // TODO: Mail support
  // TODO: Add/remove answers

}
