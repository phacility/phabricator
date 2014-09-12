<?php

final class FundInitiativeEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorFundApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Fund Initiatives');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = FundInitiativeTransaction::TYPE_NAME;
    $types[] = FundInitiativeTransaction::TYPE_DESCRIPTION;
    $types[] = FundInitiativeTransaction::TYPE_STATUS;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
        return $object->getName();
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case FundInitiativeTransaction::TYPE_STATUS:
        return $object->getStatus();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
      case FundInitiativeTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
      case FundInitiativeTransaction::TYPE_STATUS:
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
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
      case FundInitiativeTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Initiative name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }


}
