<?php

final class NuanceItemTransaction
  extends NuanceTransaction {

  const PROPERTY_KEY = 'property.key';

  const TYPE_OWNER = 'nuance.item.owner';
  const TYPE_REQUESTOR = 'nuance.item.requestor';
  const TYPE_SOURCE = 'nuance.item.source';
  const TYPE_PROPERTY = 'nuance.item.property';

  public function getApplicationTransactionType() {
    return NuanceItemPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceItemTransactionComment();
  }

}
