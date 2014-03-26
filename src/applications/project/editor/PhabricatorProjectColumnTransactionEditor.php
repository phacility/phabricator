<?php

final class PhabricatorProjectColumnTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorProjectColumnTransaction::TYPE_NAME;
    $types[] = PhabricatorProjectColumnTransaction::TYPE_STATUS;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorProjectColumnTransaction::TYPE_STATUS:
        return $object->getStatus();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
      case PhabricatorProjectColumnTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorProjectColumnTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
      case PhabricatorProjectColumnTransaction::TYPE_STATUS:
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
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Column name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }


  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
      case PhabricatorProjectColumnTransaction::TYPE_STATUS:
        PhabricatorPolicyFilter::requireCapability(
          $this->requireActor(),
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        return;
    }

    return parent::requireCapabilities($object, $xaction);
  }

}
