<?php

final class PhabricatorProjectColumnTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Workboard Columns');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorProjectColumnTransaction::TYPE_NAME;
    $types[] = PhabricatorProjectColumnTransaction::TYPE_STATUS;
    $types[] = PhabricatorProjectColumnTransaction::TYPE_LIMIT;

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
      case PhabricatorProjectColumnTransaction::TYPE_LIMIT:
        return $object->getPointLimit();

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
      case PhabricatorProjectColumnTransaction::TYPE_LIMIT:
        if ($xaction->getNewValue()) {
          return (int)$xaction->getNewValue();
        }
        return null;
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
      case PhabricatorProjectColumnTransaction::TYPE_LIMIT:
        $object->setPointLimit($xaction->getNewValue());
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
      case PhabricatorProjectColumnTransaction::TYPE_LIMIT:
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
      case PhabricatorProjectColumnTransaction::TYPE_LIMIT:
        foreach ($xactions as $xaction) {
          $value = $xaction->getNewValue();
          if (strlen($value) && !preg_match('/^\d+\z/', $value)) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('Column point limit must be empty, or a positive integer.'),
              $xaction);
          }
        }
        break;
      case PhabricatorProjectColumnTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        // The default "Backlog" column is allowed to be unnamed, which
        // means we use the default name.

        if ($missing && !$object->isDefaultColumn()) {
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
