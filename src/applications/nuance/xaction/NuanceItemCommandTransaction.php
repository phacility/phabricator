<?php

final class NuanceItemCommandTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.command';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    // TODO: Probably implement this.
  }

  public function getTitle() {
    return pht(
      '%s applied a command to this item.',
      $this->renderAuthor());
  }

}
