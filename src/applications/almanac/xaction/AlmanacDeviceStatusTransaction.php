<?php

final class AlmanacDeviceStatusTransaction
  extends AlmanacDeviceTransactionType {

  const TRANSACTIONTYPE = 'almanac:device:status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    $old_status = AlmanacDeviceStatus::newStatusFromValue($old_value);
    $new_status = AlmanacDeviceStatus::newStatusFromValue($new_value);

    $old_name = $old_status->getName();
    $new_name = $new_status->getName();

    return pht(
      '%s changed the status of this device from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_name),
      $this->renderValue($new_name));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $status_map = AlmanacDeviceStatus::getStatusMap();

    $old_value = $this->generateOldValue($object);
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if ($new_value === $old_value) {
        continue;
      }

      if (!isset($status_map[$new_value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Almanac device status "%s" is unrecognized. Valid status '.
            'values are: %s.',
            $new_value,
            implode(', ', array_keys($status_map))),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
