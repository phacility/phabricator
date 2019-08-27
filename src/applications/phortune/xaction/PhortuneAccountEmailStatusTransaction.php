<?php

final class PhortuneAccountEmailStatusTransaction
  extends PhortuneAccountEmailTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the status for this address to %s.',
      $this->renderAuthor(),
      $this->renderNewValue());
  }

}
