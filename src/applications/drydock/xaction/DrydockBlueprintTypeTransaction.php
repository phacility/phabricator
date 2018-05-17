<?php

final class DrydockBlueprintTypeTransaction
  extends DrydockBlueprintTransactionType {

  const TRANSACTIONTYPE = 'drydock.blueprint.type';

  public function generateOldValue($object) {
    return $object->getClassName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setClassName($value);
  }

  public function getTitle() {
    // These transactions can only be applied during object creation and never
    // generate a timeline event.
    return null;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $name = $object->getClassName();
    if ($this->isEmptyTextTransaction($name, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('You must select a blueprint type when creating a blueprint.'));
    }

    $map = DrydockBlueprintImplementation::getAllBlueprintImplementations();

    foreach ($xactions as $xaction) {
      if (!$this->isNewObject()) {
        $errors[] = $this->newInvalidError(
          pht(
            'The type of a blueprint can not be changed once it has '.
            'been created.'),
          $xaction);
        continue;
      }

      $new = $xaction->getNewValue();
      if (!isset($map[$new])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Blueprint type "%s" is not valid. Valid types are: %s.',
            $new,
            implode(', ', array_keys($map))));
        continue;
      }
    }

    return $errors;
  }

}
