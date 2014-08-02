<?php

final class PhabricatorAuditEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    // TODO: These will get modernized eventually, but that can happen one
    // at a time later on.
    $types[] = PhabricatorAuditActionConstants::ACTION;
    $types[] = PhabricatorAuditActionConstants::INLINE;
    $types[] = PhabricatorAuditActionConstants::ADD_AUDITORS;

    return $types;
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return $xaction->hasComment();
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
        return null;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        // TODO: For now, just record the added PHIDs. Eventually, turn these
        // into real edge transactions, probably?
        return array();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorAuditActionConstants::ACTION:
      case PhabricatorAuditActionConstants::INLINE:
        return;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        // TODO: For now, these are applied externally.
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function sortTransactions(array $xactions) {
    $xactions = parent::sortTransactions($xactions);

    $head = array();
    $tail = array();

    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == PhabricatorAuditActionConstants::INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }

}
