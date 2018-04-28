<?php

final class AlmanacServiceTypeTransaction
  extends AlmanacServiceTransactionType {

  const TRANSACTIONTYPE = 'almanac:service:type';

  public function generateOldValue($object) {
    return $object->getServiceType();
  }

  public function applyInternalEffects($object, $value) {
    $object->setServiceType($value);
  }

  public function getTitle() {
    // This transaction can only be applied during object creation via
    // Conduit and never generates a timeline event.
    return null;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getServiceType(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('You must select a service type when creating a service.'));
    }

    $map = AlmanacServiceType::getAllServiceTypes();

    foreach ($xactions as $xaction) {
      if (!$this->isNewObject()) {
        $errors[] = $this->newInvalidError(
          pht(
            'The type of a service can not be changed once it has '.
            'been created.'),
          $xaction);
        continue;
      }

      $new = $xaction->getNewValue();
      if (!isset($map[$new])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Service type "%s" is not valid. Valid types are: %s.',
            $new,
            implode(', ', array_keys($map))));
        continue;
      }
    }

    return $errors;
  }
}
