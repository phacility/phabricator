<?php

final class PassphraseCredentialLookedAtTransaction
  extends PassphraseCredentialTransactionType {

  const TRANSACTIONTYPE = 'passphrase:lookedAtSecret';

  public function generateOldValue($object) {
    return null;
  }

  public function getTitle() {
    return pht(
      '%s examined the secret plaintext for this credential.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s examined the secret plaintext for credential %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-eye';
  }

  public function getColor() {
    return 'blue';
  }

  public function shouldTryMFA(
    $object,
    PhabricatorApplicationTransaction $xaction) {
    return true;
  }

}
