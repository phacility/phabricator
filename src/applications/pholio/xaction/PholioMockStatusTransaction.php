<?php

final class PholioMockStatusTransaction
  extends PholioMockTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new == PholioMock::STATUS_CLOSED) {
      return pht(
        '%s closed this mock.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s opened this mock.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    if ($new == PholioMock::STATUS_CLOSED) {
      return pht(
        '%s closed mock %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s opened mock %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();

    if ($new == PholioMock::STATUS_CLOSED) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }

  public function getColor() {
    $new = $this->getNewValue();

    if ($new == PholioMock::STATUS_CLOSED) {
      return PhabricatorTransactions::COLOR_INDIGO;
    } else {
      return PhabricatorTransactions::COLOR_GREEN;
    }
  }

}
