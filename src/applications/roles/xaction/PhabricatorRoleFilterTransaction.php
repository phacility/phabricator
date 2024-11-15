<?php

final class PhabricatorRoleFilterTransaction
  extends PhabricatorRoleTransactionType {

  const TRANSACTIONTYPE = 'role:filter';

  public function generateOldValue($object) {
    return $object->getDefaultWorkboardFilter();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDefaultWorkboardFilter($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the default filter for the role workboard.',
      $this->renderAuthor());
  }

  public function shouldHide() {
    return true;
  }

}
