<?php

final class FundBackerStatusTransaction
  extends FundBackerTransactionType {

  const TRANSACTIONTYPE = 'fund:backer:status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }


}
