<?php

final class PassphraseCredentialUsernameTransaction
  extends PassphraseCredentialTransactionType {

  const TRANSACTIONTYPE = 'passphrase:username';

  public function generateOldValue($object) {
    return $object->getUsername();
  }

  public function applyInternalEffects($object, $value) {
    $object->setUsername($value);
  }

  public function getTitle() {
    return pht(
      '%s set the username for this credential to %s.',
      $this->renderAuthor(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s set the username for credential %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $credential_type = $object->getImplementation();
    if ($credential_type->shouldRequireUsername()) {
      if ($this->isEmptyTextTransaction($object->getUsername(), $xactions)) {
        $errors[] = $this->newRequiredError(
          pht('This credential must have a username.'));
      }
    }

    $max_length = $object->getColumnMaximumByteLength('username');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The username can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
