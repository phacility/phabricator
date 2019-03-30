<?php

final class PhabricatorProjectColumnLimitTransaction
  extends PhabricatorProjectColumnTransactionType {

  const TRANSACTIONTYPE = 'project:col:limit';

  public function generateOldValue($object) {
    return $object->getPointLimit();
  }

  public function generateNewValue($object, $value) {
    if (strlen($value)) {
      return (int)$value;
    } else {
      return null;
    }
  }

  public function applyInternalEffects($object, $value) {
    $object->setPointLimit($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$old) {
      return pht(
        '%s set the point limit for this column to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if (!$new) {
      return pht(
        '%s removed the point limit for this column.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s changed the point limit for this column from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      if (strlen($value) && !preg_match('/^\d+\z/', $value)) {
        $errors[] = $this->newInvalidError(
          pht(
            'Column point limit must either be empty or a nonnegative '.
            'integer.'),
          $xaction);
      }
    }

    return $errors;
  }

}
