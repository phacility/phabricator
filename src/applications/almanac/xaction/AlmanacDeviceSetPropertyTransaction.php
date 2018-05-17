<?php

final class AlmanacDeviceSetPropertyTransaction
  extends AlmanacDeviceTransactionType {

  const TRANSACTIONTYPE = 'almanac:property:update';

  public function generateOldValue($object) {
    return $this->getAlmanacPropertyOldValue($object);
  }

  public function applyExternalEffects($object, $value) {
    return $this->setAlmanacProperty($object, $value);
  }

  public function getTitle() {
    return $this->getAlmanacSetPropertyTitle();
  }

  public function validateTransactions($object, array $xactions) {
    return $this->validateAlmanacSetPropertyTransactions($object, $xactions);
  }

}
