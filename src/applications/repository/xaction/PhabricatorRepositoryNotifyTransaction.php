<?php

final class PhabricatorRepositoryNotifyTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:notify';

  public function generateOldValue($object) {
    return (int)!$object->getDetail('herald-disabled');
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('herald-disabled', (int)!$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s enabled publishing for this repository.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s disabled publishing for this repository.',
        $this->renderAuthor());
    }
  }

}
