<?php

final class AlmanacInterfacePortTransaction
  extends AlmanacInterfaceTransactionType {

  const TRANSACTIONTYPE = 'almanac:interface:port';

  public function generateOldValue($object) {
    $port = $object->getPort();

    if ($port !== null) {
      $port = (int)$port;
    }

    return $port;
  }

  public function applyInternalEffects($object, $value) {
    $object->setPort((int)$value);
  }

  public function getTitle() {
    return pht(
      '%s changed the port for this interface from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getPort(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Interfaces must have a port number.'));
    }

    foreach ($xactions as $xaction) {
      $port = $xaction->getNewValue();

      $port = (int)$port;
      if ($port < 1 || $port > 65535) {
        $errors[] = $this->newInvalidError(
          pht('Port numbers must be between 1 and 65535, inclusive.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
