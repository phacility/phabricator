<?php

final class PhabricatorCountdownEpochTransaction
  extends PhabricatorCountdownTransactionType {

  const TRANSACTIONTYPE = 'countdown:epoch';

  public function generateOldValue($object) {
    return (int)$object->getEpoch();
  }

  public function generateNewValue($object, $value) {
    return $value->newPhutilDateTime()
      ->newAbsoluteDateTime()
      ->getEpoch();
  }

  public function applyInternalEffects($object, $value) {
    $object->setEpoch($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the countdown end from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldDate(),
      $this->renderNewDate());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the countdown end for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldDate(),
      $this->renderNewDate());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$object->getEpoch() && !$xactions) {
      $errors[] = $this->newRequiredError(
        pht('You must give the countdown an end date.'));
      return $errors;
    }

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      if (!$value->isValid()) {
        $errors[] = $this->newInvalidError(
          pht('You must give the countdown an end date.'));
      }
    }

    return $errors;
  }
}
