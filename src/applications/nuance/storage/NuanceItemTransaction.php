<?php

final class NuanceItemTransaction
  extends NuanceTransaction {

  public function getApplicationTransactionType() {
    return NuancePHIDTypeItem::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceItemTransactionComment();
  }

}
