<?php

final class NuanceItemQueueTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.queue';

  public function generateOldValue($object) {
    return $object->getQueuePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setQueuePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s rerouted this item from %s to %s.',
      $this->renderAuthor(),
      $this->renderHandle($this->getOldValue()),
      $this->renderHandle($this->getNewValue()));
  }


}
