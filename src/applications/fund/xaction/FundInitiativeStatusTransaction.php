<?php

final class FundInitiativeStatusTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    if ($this->getNewValue() == FundInitiative::STATUS_CLOSED) {
      return pht(
        '%s closed this initiative.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s reopened this initiative.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue() == FundInitiative::STATUS_CLOSED) {
      return pht(
        '%s closed %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s reopened %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    if ($this->getNewValue() == FundInitiative::STATUS_CLOSED) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }


}
