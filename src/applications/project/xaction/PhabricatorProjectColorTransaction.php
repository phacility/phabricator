<?php

final class PhabricatorProjectColorTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:color';

  public function generateOldValue($object) {
    return $object->getColor();
  }

  public function applyInternalEffects($object, $value) {
    $object->setColor($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    return pht(
      "%s set this project's color to %s.",
      $this->renderAuthor(),
      $this->renderValue(PHUITagView::getShadeName($new)));
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    return pht(
      '%s set the color for %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderValue(PHUITagView::getShadeName($new)));
  }

}
