<?php

final class AlmanacBindingInterfaceTransaction
  extends AlmanacBindingTransactionType {

  const TRANSACTIONTYPE = 'almanac:binding:interface';

  public function generateOldValue($object) {
    return $object->getInterfacePHID();
  }

  public function applyInternalEffects($object, $value) {
    $interface = $this->loadInterface($value);

    $object
      ->setDevicePHID($interface->getDevicePHID())
      ->setInterfacePHID($interface->getPHID());
  }

  public function applyExternalEffects($object, $value) {

    // When we change which services a device is bound to, we need to
    // recalculate whether it is a cluster device or not so we can tell if
    // the "Can Manage Cluster Services" permission applies to it.

    $viewer = PhabricatorUser::getOmnipotentUser();
    $interface_phids = array();

    $interface_phids[] = $this->getOldValue();
    $interface_phids[] = $this->getNewValue();

    $interface_phids = array_filter($interface_phids);
    $interface_phids = array_unique($interface_phids);

    $interfaces = id(new AlmanacInterfaceQuery())
      ->setViewer($viewer)
      ->withPHIDs($interface_phids)
      ->execute();

    $device_phids = array();
    foreach ($interfaces as $interface) {
      $device_phids[] = $interface->getDevicePHID();
    }

    $device_phids = array_unique($device_phids);

    $devices = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withPHIDs($device_phids)
      ->execute();

    foreach ($devices as $device) {
      $device->rebuildClusterBindingStatus();
    }
  }

  public function getTitle() {
    if ($this->getOldValue() === null) {
      return pht(
        '%s set the interface for this binding to %s.',
        $this->renderAuthor(),
        $this->renderNewHandle());
    } else if ($this->getNewValue() == null) {
      return pht(
        '%s removed the interface for this binding.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s changed the interface for this binding from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldHandle(),
        $this->renderNewHandle());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $interface_phid = $object->getInterfacePHID();
    if ($this->isEmptyTextTransaction($interface_phid, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Bindings must specify an interface.'));
    }

    foreach ($xactions as $xaction) {
      $interface_phid = $xaction->getNewValue();

      $interface = $this->loadInterface($interface_phid);
      if (!$interface) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not bind a service to an invalid or restricted '.
            'interface.'),
          $xaction);
        continue;
      }

      $binding = id(new AlmanacBindingQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withServicePHIDs(array($object->getServicePHID()))
        ->withInterfacePHIDs(array($interface_phid))
        ->executeOne();
      if ($binding && ($binding->getID() != $object->getID())) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not bind a service to the same interface multiple '.
            'times.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

  private function loadInterface($phid) {
    return id(new AlmanacInterfaceQuery())
      ->setViewer($this->getActor())
      ->withPHIDs(array($phid))
      ->executeOne();
  }
}
