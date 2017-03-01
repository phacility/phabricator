<?php

final class PhabricatorPackagesPackageKeyTransaction
  extends PhabricatorPackagesPackageTransactionType {

  const TRANSACTIONTYPE = 'packages.package.key';

  public function generateOldValue($object) {
    return $object->getPackageKey();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPackageKey($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht(
          'Each package provided by a publisher must have a '.
          'unique package key.'));
      return $errors;
    }

    if (!$this->isNewObject()) {
      foreach ($xactions as $xaction) {
        $errors[] = $this->newInvalidError(
          pht('Once a package is created, its key can not be changed.'),
          $xaction);
      }
    }

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      try {
        PhabricatorPackagesPackage::assertValidPackageKey($value);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError($ex->getMessage(), $xaction);
      }
    }

    return $errors;
  }

}
