<?php

final class PhabricatorRoleWorkboardTransaction
  extends PhabricatorRoleTransactionType {

  const TRANSACTIONTYPE = 'role:hasworkboard';

  public function generateOldValue($object) {
    return (int)$object->getHasWorkboard();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setHasWorkboard($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s enabled the workboard for this role.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s disabled the workboard for this role.',
        $this->renderAuthor());
    }
  }

  public function shouldHide() {
    return true;
  }

}
