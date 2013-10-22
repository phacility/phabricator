<?php

final class HarbormasterBuildPlanEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = HarbormasterBuildPlanTransaction::TYPE_NAME;
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
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
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
    }
    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildPlanTransaction::TYPE_NAME:
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
        $missing_name = true;
        if (strlen($object->getName()) && empty($xactions)) {
          $missing_name = false;
        } else if (strlen(last($xactions)->getNewValue())) {
          $missing_name = false;
        }

        if ($missing_name) {
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
