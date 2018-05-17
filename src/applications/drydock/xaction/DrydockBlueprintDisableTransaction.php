<?php

final class DrydockBlueprintDisableTransaction
  extends DrydockBlueprintTransactionType {

  const TRANSACTIONTYPE = 'drydock:blueprint:disabled';

  public function generateOldValue($object) {
    return (bool)$object->getIsDisabled();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled((int)$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s disabled this blueprint.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this blueprint.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s disabled %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s enabled %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
