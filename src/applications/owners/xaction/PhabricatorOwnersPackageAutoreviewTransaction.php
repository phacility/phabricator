<?php

final class PhabricatorOwnersPackageAutoreviewTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.autoreview';

  public function generateOldValue($object) {
    return $object->getAutoReview();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (empty($map[$new])) {
        $valid = array_keys($map);

        $errors[] = $this->newInvalidError(
          pht(
            'Autoreview setting "%s" is not valid. '.
            'Valid settings are: %s.',
            $new,
            implode(', ', $valid)),
          $xaction);
      }
    }

    return $errors;
  }

  public function applyInternalEffects($object, $value) {
    $object->setAutoReview($value);
  }

  public function getTitle() {
    $map = PhabricatorOwnersPackage::getAutoreviewOptionsMap();
    $map = ipull($map, 'name');

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old = idx($map, $old, $old);
    $new = idx($map, $new, $new);

    return pht(
      '%s adjusted autoreview from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old),
      $this->renderValue($new));
  }

}
