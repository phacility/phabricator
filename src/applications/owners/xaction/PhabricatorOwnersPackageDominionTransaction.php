<?php

final class PhabricatorOwnersPackageDominionTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.dominion';

  public function generateOldValue($object) {
    return $object->getDominion();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $map = PhabricatorOwnersPackage::getDominionOptionsMap();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (empty($map[$new])) {
        $valid = array_keys($map);

        $errors[] = $this->newInvalidError(
          pht(
            'Dominion setting "%s" is not valid. '.
            'Valid settings are: %s.',
            $new,
            implode(', ', $valid)),
          $xaction);
      }
    }

    return $errors;
  }

  public function applyInternalEffects($object, $value) {
    $object->setDominion($value);
  }

  public function getTitle() {
    $map = PhabricatorOwnersPackage::getDominionOptionsMap();
    $map = ipull($map, 'short');

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old = idx($map, $old, $old);
    $new = idx($map, $new, $new);

    return pht(
      '%s adjusted package dominion rules from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

}
