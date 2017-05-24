<?php

final class NuanceTrashCommand
  extends NuanceCommandImplementation {

  const COMMANDKEY = 'trash';

  public function getCommandName() {
    return pht('Throw in Trash');
  }

  public function canApplyToItem(NuanceItem $item) {
    $type = $item->getImplementation();
    return ($type instanceof NuanceFormItemType);
  }

  public function canApplyImmediately(
    NuanceItem $item,
    NuanceItemCommand $command) {
    return true;
  }

  protected function executeCommand(
    NuanceItem $item,
    NuanceItemCommand $command) {
    $this->newStatusTransaction(NuanceItem::STATUS_CLOSED);
  }

}
