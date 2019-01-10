<?php

final class PhabricatorRepositoryCopyTimeLimitTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'limit.copy';

  public function generateOldValue($object) {
    return $object->getCopyTimeLimit();
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
    $object->setCopyTimeLimit($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old && $new) {
      return pht(
        '%s changed the copy time limit for this repository from %s seconds '.
        'to %s seconds.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if ($new) {
      return pht(
        '%s set the copy time limit for this repository to %s seconds.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s reset the copy time limit (%s seconds) for this repository '.
        'to the default value.',
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
            'Unable to parse copy time limit, specify a positive number '.
            'of seconds.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
