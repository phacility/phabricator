<?php

final class PhabricatorOwnersPackageNameTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $missing = $this->isEmptyTextTransaction(
      $object->getName(),
      $xactions);

    if ($missing) {
      $errors[] = $this->newRequiredError(
        pht('Package name is required.'),
        nonempty(last($xactions), null));
    }

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      if (preg_match('([,!])', $new)) {
        $errors[] = $this->newInvalidError(
          pht(
            'Package names may not contain commas (",") or exclamation '.
            'marks ("!"). These characters are ambiguous when package '.
            'names are parsed from the command line.'),
          $xaction);
      }
    }

    return $errors;
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s renamed this package from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

}
