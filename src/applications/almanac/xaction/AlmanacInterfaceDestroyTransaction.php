<?php

final class AlmanacInterfaceDestroyTransaction
  extends AlmanacInterfaceTransactionType {

  const TRANSACTIONTYPE = 'almanac:interface:destroy';

  public function generateOldValue($object) {
    return false;
  }

  public function applyExternalEffects($object, $value) {
    id(new PhabricatorDestructionEngine())
      ->destroyObject($object);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($xactions) {
      if ($object->loadIsInUse()) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not delete this interface because it is currently in '.
            'use. One or more services are bound to it.'));
      }
    }

    return $errors;
  }

}
