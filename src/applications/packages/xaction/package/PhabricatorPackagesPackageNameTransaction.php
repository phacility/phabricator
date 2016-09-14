<?php

final class PhabricatorPackagesPackageNameTransaction
  extends PhabricatorPackagesPackageTransactionType {

  const TRANSACTIONTYPE = 'packages.package.name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the name of this package from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the name for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Packages must have a name.'));
      return $errors;
    }

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      try {
        PhabricatorPackagesPackage::assertValidPackageName($value);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError($ex->getMessage(), $xaction);
      }
    }

    return $errors;
  }

}
