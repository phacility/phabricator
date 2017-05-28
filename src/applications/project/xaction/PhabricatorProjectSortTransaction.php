<?php

final class PhabricatorProjectSortTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:sort';

  public function generateOldValue($object) {
    return $object->getDefaultWorkboardSort();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDefaultWorkboardSort($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the default sort order for the project workboard.',
      $this->renderAuthor());
  }

  public function shouldHide() {
    return true;
  }

}
