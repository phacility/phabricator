<?php

final class PhortuneAccountEmailRotateTransaction
  extends PhortuneAccountEmailTransactionType {

  const TRANSACTIONTYPE = 'rotate';

  public function generateOldValue($object) {
    return false;
  }

  public function applyInternalEffects($object, $value) {
    $access_key = Filesystem::readRandomCharacters(16);
    $object->setAccessKey($access_key);
  }

  public function getTitle() {
    return pht(
      '%s rotated the access key for this email address.',
      $this->renderAuthor());
  }

}
