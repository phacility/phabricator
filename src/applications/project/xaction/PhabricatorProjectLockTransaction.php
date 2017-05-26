<?php

final class PhabricatorProjectLockTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:locked';

  public function generateOldValue($object) {
    return (int)$object->getIsMembershipLocked();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsMembershipLocked($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        "%s locked this project's membership.",
        $this->renderAuthor());
    } else {
      return pht(
        "%s unlocked this project's membership.",
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s locked %s membership.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s unlocked %s membership.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();

    if ($new) {
      return 'fa-lock';
    } else {
      return 'fa-unlock';
    }
  }

}
