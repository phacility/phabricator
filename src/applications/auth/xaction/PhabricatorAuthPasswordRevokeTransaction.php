<?php

final class PhabricatorAuthPasswordRevokeTransaction
  extends PhabricatorAuthPasswordTransactionType {

  const TRANSACTIONTYPE = 'password.revoke';

  public function generateOldValue($object) {
    return (bool)$object->getIsRevoked();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsRevoked((int)$value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s revoked this password.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s removed this password from the revocation list.',
        $this->renderAuthor());
    }
  }

}
