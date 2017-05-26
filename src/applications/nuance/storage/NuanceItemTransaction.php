<?php

final class NuanceItemTransaction
  extends NuanceTransaction {

  const PROPERTY_KEY = 'property.key';

  public function getApplicationTransactionType() {
    return NuanceItemPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceItemTransactionComment();
  }

  public function getBaseTransactionClass() {
    return 'NuanceItemTransactionType';
  }

}
