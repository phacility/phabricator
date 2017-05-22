<?php

final class NuanceItemSourceTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.source';

  public function generateOldValue($object) {
    return $object->getSourcePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setSourcePHID($value);
  }

}
