<?php

final class PassphraseCredentialConduitTransaction
  extends PassphraseCredentialTransactionType {

  const TRANSACTIONTYPE = 'passphrase:conduit';

  public function generateOldValue($object) {
    return $object->getAllowConduit();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAllowConduit((int)$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s allowed Conduit API access to this credential.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s disallowed Conduit API access to this credential.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s allowed Conduit API access to credential %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s disallowed Conduit API access to credential %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    if ($new) {
      return 'fa-tty';
    } else {
      return 'fa-ban';
    }
  }

}
