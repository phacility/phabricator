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
      '%s changed the title of this paste from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the title for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
