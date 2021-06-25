<?php

final class PhabricatorOwnersPackageAuthorityTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.authority';

  public function generateOldValue($object) {
    return $object->getAuthorityMode();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $map = PhabricatorOwnersPackage::getAuthorityOptionsMap();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (empty($map[$new])) {
        $valid = array_keys($map);

        $errors[] = $this->newInvalidError(
          pht(
            'Authority setting "%s" is not valid. '.
            'Valid settings are: %s.',
            $new,
            implode(', ', $valid)),
          $xaction);
      }
    }

    return $errors;
  }

  public function applyInternalEffects($object, $value) {
    $object->setAuthorityMode($value);
  }

  public function getTitle() {
    $map = PhabricatorOwnersPackage::getAuthorityOptionsMap();
    $map = ipull($map, 'short');

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old = idx($map, $old, $old);
    $new = idx($map, $new, $new);

    return pht(
      '%s adjusted package authority rules from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

}
