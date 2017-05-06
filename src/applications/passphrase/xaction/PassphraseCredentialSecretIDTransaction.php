<?php

final class PassphraseCredentialSecretIDTransaction
  extends PassphraseCredentialTransactionType {

  const TRANSACTIONTYPE = 'passphrase:secretID';

  public function generateOldValue($object) {
    return $object->getSecretID();
  }

  public function applyInternalEffects($object, $value) {
    $old_id = $object->getSecretID();
    if ($old_id) {
      $this->destroySecret($old_id);
    }
    $object->setSecretID($value);
  }

  public function shouldHide() {
    if (!$this->getOldValue()) {
      return true;
    }

    return false;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if ($old === null) {
      return pht(
        '%s attached a new secret to this credential.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s updated the secret for this credential.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if ($old === null) {
      return pht(
        '%s attached a new secret to %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s updated the secret for %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
