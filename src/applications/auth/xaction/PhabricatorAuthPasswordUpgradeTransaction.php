<?php

final class PhabricatorAuthPasswordUpgradeTransaction
  extends PhabricatorAuthPasswordTransactionType {

  const TRANSACTIONTYPE = 'password.upgrade';

  public function generateOldValue($object) {
    return $this->getStorage()->getOldValue();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function getTitle() {
    return pht(
      '%s upgraded the hash algorithm for this password from "%s" to "%s".',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
