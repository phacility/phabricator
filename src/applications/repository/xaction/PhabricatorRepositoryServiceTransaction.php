<?php

final class PhabricatorRepositoryServiceTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:service';

  public function generateOldValue($object) {
    return $object->getAlmanacServicePHID();
  }

  public function generateNewValue($object, $value) {
    if (strlen($value)) {
      return $value;
    }

    return null;
  }

  public function applyInternalEffects($object, $value) {
    $object->setAlmanacServicePHID($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getOldValue();

    if (strlen($old) && !strlen($new)) {
      return pht(
        '%s moved storage for this repository from %s to local.',
        $this->renderAuthor(),
        $this->renderOldHandle());
    } else if (!strlen($old) && strlen($new)) {
      // TODO: Possibly, we should distinguish between automatic assignment
      // on creation vs explicit adjustment.
      return pht(
        '%s set storage for this repository to %s.',
        $this->renderAuthor(),
        $this->renderNewHandle());
    } else {
      return pht(
        '%s moved storage for this repository from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldHandle(),
        $this->renderNewHandle());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // TODO: This could use some validation, values should be valid Almanac
    // services of appropriate types. It's only reachable via the CLI so it's
    // difficult to get wrong in practice.

    return $errors;
  }

}
