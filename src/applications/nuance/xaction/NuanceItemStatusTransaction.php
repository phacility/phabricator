<?php

final class NuanceItemStatusTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the status of this item from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }


}
