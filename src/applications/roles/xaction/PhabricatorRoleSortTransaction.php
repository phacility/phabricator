<?php

final class PhabricatorRoleSortTransaction
  extends PhabricatorRoleTransactionType {

  const TRANSACTIONTYPE = 'role:sort';

  public function generateOldValue($object) {
    return $object->getDefaultWorkboardSort();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDefaultWorkboardSort($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the default sort order for the role workboard.',
      $this->renderAuthor());
  }

  public function shouldHide() {
    return true;
  }

}
