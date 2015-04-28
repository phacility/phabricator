<?php

final class PhabricatorCalendarEventEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Calendar');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorCalendarEventTransaction::TYPE_START_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_END_DATE;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_STATUS;
    $types[] = PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        return $object->getDateFrom();
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        return $object->getDateTo();
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        $status = $object->getStatus();
        if ($status === null) {
          return null;
        }
        return (int)$status;
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        return $xaction->getNewValue();
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        return (int)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
        $object->setDateFrom($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
        $object->setDateTo($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCalendarEventTransaction::TYPE_START_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_END_DATE:
      case PhabricatorCalendarEventTransaction::TYPE_STATUS:
      case PhabricatorCalendarEventTransaction::TYPE_DESCRIPTION:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }
}
