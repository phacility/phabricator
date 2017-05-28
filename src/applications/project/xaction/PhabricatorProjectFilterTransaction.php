<?php

final class PhabricatorProjectFilterTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:filter';

  public function generateOldValue($object) {
    return $object->getDefaultWorkboardFilter();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDefaultWorkboardFilter($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the default filter for the project workboard.',
      $this->renderAuthor());
  }

  public function shouldHide() {
    return true;
  }

}
