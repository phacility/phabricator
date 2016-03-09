<?php

final class DrydockBlueprintEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDrydockApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Drydock Blueprints');
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = DrydockBlueprintTransaction::TYPE_NAME;
    $types[] = DrydockBlueprintTransaction::TYPE_DISABLED;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DrydockBlueprintTransaction::TYPE_NAME:
        return $object->getBlueprintName();
      case DrydockBlueprintTransaction::TYPE_DISABLED:
        return (int)$object->getIsDisabled();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DrydockBlueprintTransaction::TYPE_NAME:
        return $xaction->getNewValue();
      case DrydockBlueprintTransaction::TYPE_DISABLED:
        return (int)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DrydockBlueprintTransaction::TYPE_NAME:
        $object->setBlueprintName($xaction->getNewValue());
        return;
      case DrydockBlueprintTransaction::TYPE_DISABLED:
        $object->setIsDisabled((int)$xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DrydockBlueprintTransaction::TYPE_NAME:
      case DrydockBlueprintTransaction::TYPE_DISABLED:
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
      case DrydockBlueprintTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getBlueprintName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('You must choose a name for this blueprint.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
          continue;
        }

        break;
    }

    return $errors;
  }

}
