<?php

final class PhabricatorDashboardPanelTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Dashboard Panels');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDGE;

    $types[] = PhabricatorDashboardPanelTransaction::TYPE_NAME;
    $types[] = PhabricatorDashboardPanelTransaction::TYPE_ARCHIVE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorDashboardPanelTransaction::TYPE_NAME:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getName();
      case PhabricatorDashboardPanelTransaction::TYPE_ARCHIVE:
        return (int)$object->getIsArchived();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorDashboardPanelTransaction::TYPE_NAME:
        return $xaction->getNewValue();
      case PhabricatorDashboardPanelTransaction::TYPE_ARCHIVE:
        return (int)$xaction->getNewValue();
    }
    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorDashboardPanelTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorDashboardPanelTransaction::TYPE_ARCHIVE:
        $object->setIsArchived((int)$xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorDashboardPanelTransaction::TYPE_NAME:
      case PhabricatorDashboardPanelTransaction::TYPE_ARCHIVE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorDashboardPanelTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Panel name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }


}
