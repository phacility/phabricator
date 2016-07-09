<?php

final class PhabricatorPasteStatusTransaction
  extends PhabricatorPasteTransactionType {

  const TRANSACTIONTYPE = 'paste.status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  private function isActivate() {
    return ($this->getNewValue() == PhabricatorPaste::STATUS_ACTIVE);
  }

  public function getIcon() {
    if ($this->isActivate()) {
      return 'fa-check';
    } else {
      return 'fa-ban';
    }
  }

  public function getColor() {
    if ($this->isActivate()) {
      return 'green';
    } else {
      return 'indigo';
    }
  }

  public function getTitle() {
    if ($this->isActivate()) {
      return pht(
        '%s activated this paste.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s archived this paste.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->isActivate()) {
      return pht(
        '%s activated %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s archived %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
