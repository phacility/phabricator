<?php

final class DrydockBlueprintNameTransaction
  extends DrydockBlueprintTransactionType {

  const TRANSACTIONTYPE = 'drydock:blueprint:name';

  public function generateOldValue($object) {
    return $object->getBlueprintName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setBlueprintName($value);
  }

  public function getTitle() {
    return pht(
      '%s renamed this blueprint from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s renamed %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $name = $object->getBlueprintName();
    if ($this->isEmptyTextTransaction($name, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Blueprints must have a name.'));
    }

    $max_length = $object->getColumnMaximumByteLength('blueprintName');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('Blueprint names can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
