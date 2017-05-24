<?php

final class NuanceItemCommandTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.command';

  public function generateOldValue($object) {
    return null;
  }

  public function getTitle() {
    $spec = $this->getNewValue();
    $command_key = idx($spec, 'command', '???');

    return pht(
      '%s applied a "%s" command to this item.',
      $this->renderAuthor(),
      $command_key);
  }

}
