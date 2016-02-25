<?php

final class AlmanacBindingEditor
  extends AlmanacEditor {

  private $devicePHID;

  public function getEditorObjectsDescription() {
    return pht('Almanac Binding');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = AlmanacBindingTransaction::TYPE_INTERFACE;
    $types[] = AlmanacBindingTransaction::TYPE_DISABLE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        return $object->getInterfacePHID();
      case AlmanacBindingTransaction::TYPE_DISABLE:
        return $object->getIsDisabled();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        return $xaction->getNewValue();
      case AlmanacBindingTransaction::TYPE_DISABLE:
        return (int)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        $interface = id(new AlmanacInterfaceQuery())
          ->setViewer($this->requireActor())
          ->withPHIDs(array($xaction->getNewValue()))
          ->executeOne();
        $object->setDevicePHID($interface->getDevicePHID());
        $object->setInterfacePHID($interface->getPHID());
        return;
      case AlmanacBindingTransaction::TYPE_DISABLE:
        $object->setIsDisabled($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_DISABLE:
        return;
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        $interface_phids = array();

        $interface_phids[] = $xaction->getOldValue();
        $interface_phids[] = $xaction->getNewValue();

        $interface_phids = array_filter($interface_phids);
        $interface_phids = array_unique($interface_phids);

        $interfaces = id(new AlmanacInterfaceQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs($interface_phids)
          ->execute();

        $device_phids = array();
        foreach ($interfaces as $interface) {
          $device_phids[] = $interface->getDevicePHID();
        }

        $device_phids = array_unique($device_phids);

        $devices = id(new AlmanacDeviceQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs($device_phids)
          ->execute();

        foreach ($devices as $device) {
          $device->rebuildClusterBindingStatus();
        }
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        $missing = $this->validateIsEmptyTextField(
          $object->getInterfacePHID(),
          $xactions);
        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Bindings must specify an interface.'),
            nonempty(last($xactions), null));
          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        } else if ($xactions) {
          foreach ($xactions as $xaction) {
            $interfaces = id(new AlmanacInterfaceQuery())
              ->setViewer($this->requireActor())
              ->withPHIDs(array($xaction->getNewValue()))
              ->execute();
            if (!$interfaces) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'You can not bind a service to an invalid or restricted '.
                  'interface.'),
                $xaction);
              $errors[] = $error;
            }
          }

          $final_value = last($xactions)->getNewValue();

          $binding = id(new AlmanacBindingQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withServicePHIDs(array($object->getServicePHID()))
            ->withInterfacePHIDs(array($final_value))
            ->executeOne();
          if ($binding && ($binding->getID() != $object->getID())) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Already Bound'),
              pht(
                'You can not bind a service to the same interface multiple '.
                'times.'),
              last($xactions));
            $errors[] = $error;
          }
        }
        break;
    }

    return $errors;
  }



}
