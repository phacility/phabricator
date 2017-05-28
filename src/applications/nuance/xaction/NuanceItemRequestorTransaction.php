<?php

final class NuanceItemRequestorTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.requestor';

  public function generateOldValue($object) {
    return $object->getRequestorPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRequestorPHID($value);
  }

}
