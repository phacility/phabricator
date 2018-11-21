<?php

final class PhabricatorRepositoryDangerousTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:dangerous';

  public function generateOldValue($object) {
    return $object->shouldAllowDangerousChanges();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('allow-dangerous-changes', $value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s disabled protection against dangerous changes.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled protection against dangerous changes.',
        $this->renderAuthor());
    }
  }

}
