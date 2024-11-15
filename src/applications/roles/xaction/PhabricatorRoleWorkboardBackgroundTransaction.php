<?php

final class PhabricatorRoleWorkboardBackgroundTransaction
  extends PhabricatorRoleTransactionType {

  const TRANSACTIONTYPE = 'role:background';

  public function generateOldValue($object) {
    return $object->getWorkboardBackgroundColor();
  }

  public function applyInternalEffects($object, $value) {
    $object->setWorkboardBackgroundColor($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the background color of the role workboard.',
      $this->renderAuthor());
  }

  public function shouldHide() {
    return true;
  }

}
