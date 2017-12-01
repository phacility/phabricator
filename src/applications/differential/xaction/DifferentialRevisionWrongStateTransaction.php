<?php

final class DifferentialRevisionWrongStateTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.wrong';

  public function generateOldValue($object) {
    return null;
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function getIcon() {
    return 'fa-exclamation';
  }

  public function getColor() {
    return 'pink';
  }

  public function getActionStrength() {
    return 4;
  }

  public function getTitle() {
    $new_value = $this->getNewValue();

    $status = DifferentialRevisionStatus::newForStatus($new_value);

    return pht(
      'This revision was not accepted when it landed; it landed in state %s.',
      $this->renderValue($status->getDisplayName()));
  }

  public function getTitleForFeed() {
    return null;
  }
}
