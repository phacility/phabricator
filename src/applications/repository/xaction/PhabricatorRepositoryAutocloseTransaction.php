<?php

final class PhabricatorRepositoryAutocloseTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:autoclose';

  public function generateOldValue($object) {
    return (int)!$object->getDetail('disable-autoclose');
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('disable-autoclose', (int)!$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s enabled autoclose for this repository.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s disabled autoclose for this repository.',
        $this->renderAuthor());
    }
  }

}
