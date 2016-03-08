<?php

final class NuanceGitHubEventItemType
  extends NuanceItemType {

  const ITEMTYPE = 'github.event';

  public function canUpdateItems() {
    return true;
  }

  protected function updateItemFromSource(NuanceItem $item) {
    // TODO: Link up the requestor, etc.

    if ($item->getStatus() == NuanceItem::STATUS_IMPORTING) {
      $item
        ->setStatus(NuanceItem::STATUS_ROUTING)
        ->save();
    }
  }

}
