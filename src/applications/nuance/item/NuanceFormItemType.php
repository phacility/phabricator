<?php

final class NuanceFormItemType
  extends NuanceItemType {

  const ITEMTYPE = 'form.item';

  public function getItemTypeDisplayName() {
    return pht('Form');
  }

  public function getItemDisplayName(NuanceItem $item) {
    return pht('Complaint');
  }

}
