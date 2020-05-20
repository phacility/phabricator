<?php

final class PhortunePaymentMethodNameTransaction
  extends PhortunePaymentMethodTransactionType {

  const TRANSACTIONTYPE = 'name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    if (strlen($old_value) && strlen($new_value)) {
      return pht(
        '%s renamed this payment method from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($new_value)) {
      return pht(
        '%s set the name of this payment method to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s removed the name of this payment method (was: %s).',
        $this->renderAuthor(),
        $this->renderOldValue());
    }
  }

}
