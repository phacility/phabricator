<?php

final class PhabricatorAuthPasswordUpgradeTransaction
  extends PhabricatorAuthPasswordTransactionType {

  const TRANSACTIONTYPE = 'password.upgrade';

  public function generateOldValue($object) {
    $old_hasher = $this->getEditor()->getOldHasher();

    if (!$old_hasher) {
      throw new PhutilInvalidStateException('setOldHasher');
    }

    return $old_hasher->getHashName();
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function getTitle() {
    return pht(
      '%s upgraded the hash algorithm for this password from "%s" to "%s".',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
