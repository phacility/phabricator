<?php

final class PhabricatorRepositoryTouchLimitTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'limit.touch';

  public function generateOldValue($object) {
    return $object->getTouchLimit();
  }

  public function generateNewValue($object, $value) {
    if (!strlen($value)) {
      return null;
    }

    $value = (int)$value;
    if (!$value) {
      return null;
    }

    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setTouchLimit($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old && $new) {
      return pht(
        '%s changed the touch limit for this repository from %s paths to '.
        '%s paths.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if ($new) {
      return pht(
        '%s set the touch limit for this repository to %s paths.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s removed the touch limit (%s paths) for this repository.',
        $this->renderAuthor(),
        $this->renderOldValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (!strlen($new)) {
        continue;
      }

      if (!preg_match('/^\d+\z/', $new)) {
        $errors[] = $this->newInvalidError(
          pht(
            'Unable to parse touch limit, specify a positive number of '.
            'paths.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
