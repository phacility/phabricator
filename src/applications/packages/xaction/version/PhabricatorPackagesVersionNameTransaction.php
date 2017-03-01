<?php

final class PhabricatorPackagesVersionNameTransaction
  extends PhabricatorPackagesVersionTransactionType {

  const TRANSACTIONTYPE = 'packages.version.name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the name of this version from %s to %s.',
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
        pht('Versions must have a name.'));
      return $errors;
    }

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      try {
        PhabricatorPackagesVersion::assertValidVersionName($value);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError($ex->getMessage(), $xaction);
      }
    }

    if (!$this->isNewObject()) {
      foreach ($xactions as $xaction) {
        $errors[] = $this->newInvalidError(
          pht('Once a version is created, its name can not be changed.'),
          $xaction);
      }
    }

    return $errors;
  }

}
