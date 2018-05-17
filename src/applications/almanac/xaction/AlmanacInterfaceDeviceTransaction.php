<?php

final class AlmanacInterfaceDeviceTransaction
  extends AlmanacInterfaceTransactionType {

  const TRANSACTIONTYPE = 'almanac:interface:device';

  public function generateOldValue($object) {
    return $object->getDevicePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDevicePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the device for this interface from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $device_phid = $object->getDevicePHID();
    if ($this->isEmptyTextTransaction($device_phid, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Interfaces must have a device.'));
    }

    foreach ($xactions as $xaction) {
      if (!$this->isNewObject()) {
        $errors[] = $this->newInvalidError(
          pht(
            'The device for an interface can not be changed once it has '.
            'been created.'),
          $xaction);
        continue;
      }

      $device_phid = $xaction->getNewValue();
      $devices = id(new AlmanacDeviceQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($device_phid))
        ->execute();
      if (!$devices) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not attach an interface to a nonexistent or restricted '.
            'device.'),
          $xaction);
        continue;
      }

      $device = head($devices);
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $this->getActor(),
        $device,
        PhabricatorPolicyCapability::CAN_EDIT);
      if (!$can_edit) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not attach an interface to a device which you do not '.
            'have permission to edit.'));
        continue;
      }
    }

    return $errors;
  }

}
