<?php

final class NuanceRequestorTransaction
  extends NuanceTransaction {

  const PROPERTY_KEY = 'property.key';

  const TYPE_PROPERTY = 'nuance.requestor.property';

  public function getApplicationTransactionType() {
    return NuanceRequestorPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceRequestorTransactionComment();
  }
}
