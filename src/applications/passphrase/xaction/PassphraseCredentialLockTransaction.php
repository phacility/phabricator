<?php

final class PassphraseCredentialLockTransaction
  extends PassphraseCredentialTransactionType {

  const TRANSACTIONTYPE = 'passphrase:lock';

  public function generateOldValue($object) {
    return $object->getIsLocked();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsLocked((int)$value);
  }

  public function shouldHide() {
    $new = $this->getNewValue();
    if ($new === null) {
      return true;
    }
    return false;
  }

  public function getTitle() {
    return pht(
      '%s locked this credential.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s locked credential %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-lock';
  }

}
