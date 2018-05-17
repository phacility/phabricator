<?php

final class AlmanacServiceDeletePropertyTransaction
  extends AlmanacServiceTransactionType {

  const TRANSACTIONTYPE = 'almanac:property:remove';

  public function generateOldValue($object) {
    return $this->getAlmanacPropertyOldValue($object);
  }

  public function applyInternalEffects($object, $value) {
    return $this->deleteAlmanacProperty($object);
  }

  public function getTitle() {
    return $this->getAlmanacDeletePropertyTitle();
  }

}
