<?php

final class AlmanacBindingDisableTransaction
  extends AlmanacBindingTransactionType {

  const TRANSACTIONTYPE = 'almanac:binding:disable';

  public function generateOldValue($object) {
    return (bool)$object->getIsDisabled();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled((int)$value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s disabled this binding.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this binding.',
        $this->renderAuthor());
    }
  }

}
