<?php

final class PhabricatorPasteTitleTransaction
  extends PhabricatorPasteTransactionType {

  const TRANSACTIONTYPE = 'paste.title';

  public function generateOldValue($object) {
    return $object->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTitle($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the paste\'s title from "%s" to "%s".',
      $this->renderAuthor(),
      $this->getOldValue(),
      $this->getNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the title for %s from "%s" to "%s".',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->getOldValue(),
      $this->getNewValue());
  }

}
