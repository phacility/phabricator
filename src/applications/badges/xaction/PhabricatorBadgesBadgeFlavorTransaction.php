<?php

final class PhabricatorBadgesBadgeFlavorTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.flavor';

  public function generateOldValue($object) {
    return $object->getFlavor();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFlavor($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the flavor from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated %s flavor text from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
