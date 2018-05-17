<?php

final class AlmanacInterfaceAddressTransaction
  extends AlmanacInterfaceTransactionType {

  const TRANSACTIONTYPE = 'almanac:interface:address';

  public function generateOldValue($object) {
    return $object->getAddress();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAddress($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the address for this interface from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getAddress(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Interfaces must have an address.'));
    }

    foreach ($xactions as $xaction) {

      // NOTE: For now, we don't validate addresses. We generally expect users
      // to provide IPv4 addresses, but it's reasonable for them to provide
      // IPv6 addresses, and some installs currently use DNS names. This is
      // off-label but works today.

    }

    return $errors;
  }

}
