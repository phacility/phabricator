<?php

final class PhabricatorRepositoryEnormousTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:enormous';

  public function generateOldValue($object) {
    return $object->shouldAllowEnormousChanges();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('allow-enormous-changes', $value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s disabled protection against enormous changes.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled protection against enormous changes.',
        $this->renderAuthor());
    }
  }

}
