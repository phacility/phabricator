<?php

final class PhabricatorMacroFileTransaction
  extends PhabricatorMacroTransactionType {

  const TRANSACTIONTYPE = 'macro:file';

  public function generateOldValue($object) {
    return $object->getFilePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFilePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the image for this macro.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the image for macro %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-file-image-o';
  }

}
