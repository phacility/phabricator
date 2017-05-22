<?php

final class NuanceItemOwnerTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.owner';

  public function generateOldValue($object) {
    return $object->getOwnerPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setOwnerPHID($value);
  }

  public function getTitle() {

    // TODO: Assign, unassign strings probably need variants.

    return pht(
      '%s reassigned this item from %s to %s.',
      $this->renderAuthor(),
      $this->renderHandle($this->getOldValue()),
      $this->renderHandle($this->getNewValue()));
  }

}
