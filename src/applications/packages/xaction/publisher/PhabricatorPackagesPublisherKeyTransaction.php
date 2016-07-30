<?php

final class PhabricatorPackagesPublisherKeyTransaction
  extends PhabricatorPackagesPublisherTransactionType {

  const TRANSACTIONTYPE = 'packages.publisher.key';

  public function generateOldValue($object) {
    return $object->getPublisherKey();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPublisherKey($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Publishers must have a unique publisher key.'));
    }

    if (!$this->isNewObject()) {
      foreach ($xactions as $xaction) {
        $errors[] = $this->newInvalidError(
          pht('Once a publisher is created, its key can not be changed.'),
          $xaction);
      }
    }

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      try {
        PhabricatorPackagesPublisher::assertValidPublisherKey($value);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError($ex->getMessage(), $xaction);
      }
    }

    return $errors;
  }

}
