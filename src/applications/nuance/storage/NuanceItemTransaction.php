<?php

final class NuanceItemTransaction
  extends NuanceTransaction {

  const TYPE_OWNER = 'item-owner';
  const TYPE_REQUESTOR = 'item-requestor';
  const TYPE_SOURCE = 'item-source';

  public function getApplicationTransactionType() {
    return NuancePHIDTypeItem::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceItemTransactionComment();
  }

}
