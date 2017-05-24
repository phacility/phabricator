<?php

final class PhabricatorProjectWorkboardBackgroundTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:background';

  public function generateOldValue($object) {
    return $object->getWorkboardBackgroundColor();
  }

  public function applyInternalEffects($object, $value) {
    $object->setWorkboardBackgroundColor($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the background color of the project workboard.',
      $this->renderAuthor());
  }

  public function shouldHide() {
    return true;
  }

}
