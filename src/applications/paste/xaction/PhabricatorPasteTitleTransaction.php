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
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s changed the title of this paste from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($new)) {
      return pht(
        '%s changed the title of this paste from untitled to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s changed the title of this paste from %s to untitled.',
        $this->renderAuthor(),
        $this->renderOldValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s updated the title for %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($new)) {
      return pht(
        '%s updated the title for %s from untitled to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated the title for %s from %s to untitled.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue());
    }
  }

}
