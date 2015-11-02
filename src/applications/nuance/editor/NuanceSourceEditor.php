<?php

final class NuanceSourceEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Nuance Sources');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = NuanceSourceTransaction::TYPE_NAME;
    $types[] = NuanceSourceTransaction::TYPE_DEFAULT_QUEUE;

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceSourceTransaction::TYPE_NAME:
        return $object->getName();
      case NuanceSourceTransaction::TYPE_DEFAULT_QUEUE:
        return $object->getDefaultQueuePHID();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceSourceTransaction::TYPE_NAME:
      case NuanceSourceTransaction::TYPE_DEFAULT_QUEUE:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceSourceTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        break;
      case NuanceSourceTransaction::TYPE_DEFAULT_QUEUE:
        $object->setDefaultQueuePHID($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case NuanceSourceTransaction::TYPE_NAME:
      case NuanceSourceTransaction::TYPE_DEFAULT_QUEUE:
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
      case NuanceSourceTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Source name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case NuanceSourceTransaction::TYPE_DEFAULT_QUEUE:
        foreach ($xactions as $xaction) {
          if (!$xaction->getNewValue()) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Required'),
              pht('Sources must have a default queue.'),
              $xaction);
            $error->setIsMissingFieldError(true);
            $errors[] = $error;
          }
        }
        break;
    }

    return $errors;
  }

}
