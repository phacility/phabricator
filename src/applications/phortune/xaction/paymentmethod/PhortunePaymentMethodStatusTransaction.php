<?php

final class PhortunePaymentMethodStatusTransaction
  extends PhortunePaymentMethodTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the status of this payment method.',
      $this->renderAuthor());
  }

}
