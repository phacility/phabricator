<?php

final class ManiphestTaskPointsTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'points';

  public function generateOldValue($object) {
    return $this->getValueForPoints($object->getPoints());
  }

  public function generateNewValue($object, $value) {
    return $this->getValueForPoints($value);
  }

  public function applyInternalEffects($object, $value) {
    $object->setPoints($value);
  }

  public function shouldHideForFeed() {
    return true;
  }

  public function shouldHide() {
    if (!ManiphestTaskPoints::getIsEnabled()) {
      return true;
    }
    return false;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s set the point value for this task to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if ($new === null) {
      return pht(
        '%s removed the point value for this task.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s changed the point value for this task from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      if (strlen($new) && !is_numeric($new)) {
        $errors[] = $this->newInvalidError(
          pht('Points value must be numeric or empty.'));
        continue;
      }

      if ((double)$new < 0) {
        $errors[] = $this->newInvalidError(
          pht('Points value must be nonnegative.'));
        continue;
      }
    }

    return $errors;
  }

  private function getValueForPoints($value) {
    if (!strlen($value)) {
      $value = null;
    }
    if ($value !== null) {
      $value = (double)$value;
    }
    return $value;
  }

}
