<?php

final class PassphraseCredentialDestroyTransaction
  extends PassphraseCredentialTransactionType {

  const TRANSACTIONTYPE = 'passphrase:destroy';

  public function generateOldValue($object) {
    return $object->getIsDestroyed();
  }

  public function applyInternalEffects($object, $value) {
    $is_destroyed = $value;
    $object->setIsDestroyed($is_destroyed);
    if ($is_destroyed) {
      $secret_id = $object->getSecretID();
      if ($secret_id) {
        $this->destroySecret($secret_id);
        $object->setSecretID(null);
      }
    }
  }

  public function shouldHide() {
    $new = $this->getNewValue();
    if (!$new) {
      return true;
    }
  }

  public function getTitle() {
    return pht(
      '%s destroyed the secret for this credential.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s destroyed the secret for credential %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-ban';
  }

  public function getColor() {
    return 'red';
  }

}
