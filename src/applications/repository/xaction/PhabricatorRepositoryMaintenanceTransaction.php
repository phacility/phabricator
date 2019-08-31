<?php

final class PhabricatorRepositoryMaintenanceTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'maintenance';

  public function generateOldValue($object) {
    return $object->getReadOnlyMessage();
  }

  public function applyInternalEffects($object, $value) {
    if ($value === null) {
      $object
        ->setReadOnly(false)
        ->setReadOnlyMessage(null);
    } else {
      $object
        ->setReadOnly(true)
        ->setReadOnlyMessage($value);
    }
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && !strlen($new)) {
      return pht(
        '%s took this repository out of maintenance mode.',
        $this->renderAuthor());
    } else if (!strlen($old) && strlen($new)) {
      return pht(
        '%s put this repository into maintenance mode.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s updated the maintenance message for this repository.',
        $this->renderAuthor());
    }
  }

}
