<?php

final class PhabricatorMacroDisabledTransaction
  extends PhabricatorMacroTransactionType {

  const TRANSACTIONTYPE = 'macro:disabled';

  public function generateOldValue($object) {
    return $object->getIsDisabled();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled($value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s disabled this macro.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s restored this macro.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue()) {
      return pht(
        '%s disabled %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s restored %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    if ($this->getNewValue()) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }

}
