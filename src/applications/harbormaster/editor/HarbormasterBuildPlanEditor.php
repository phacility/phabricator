<?php

final class HarbormasterBuildPlanEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Harbormaster Build Plans');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = HarbormasterBuildPlanTransaction::TYPE_NAME;
    $types[] = HarbormasterBuildPlanTransaction::TYPE_STATUS;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getName();
      case HarbormasterBuildPlanTransaction::TYPE_STATUS:
        return $object->getPlanStatus();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
        return $xaction->getNewValue();
      case HarbormasterBuildPlanTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
    }
    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case HarbormasterBuildPlanTransaction::TYPE_STATUS:
        $object->setPlanStatus($xaction->getNewValue());
        return;
    }
    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
      case HarbormasterBuildPlanTransaction::TYPE_STATUS:
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
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Plan name is required.'),
            last($xactions));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }


}
