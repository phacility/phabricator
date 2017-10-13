<?php

final class AlmanacDeviceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Device');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = AlmanacDeviceTransaction::TYPE_NAME;
    $types[] = AlmanacDeviceTransaction::TYPE_INTERFACE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case AlmanacDeviceTransaction::TYPE_NAME:
        return $object->getName();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacDeviceTransaction::TYPE_NAME:
      case AlmanacDeviceTransaction::TYPE_INTERFACE:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacDeviceTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case AlmanacDeviceTransaction::TYPE_INTERFACE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacDeviceTransaction::TYPE_NAME:
        return;
      case AlmanacDeviceTransaction::TYPE_INTERFACE:
        $old = $xaction->getOldValue();
        if ($old) {
          $interface = id(new AlmanacInterfaceQuery())
            ->setViewer($this->requireActor())
            ->withIDs(array($old['id']))
            ->executeOne();
          if (!$interface) {
            throw new Exception(pht('Unable to load interface!'));
          }
        } else {
          $interface = AlmanacInterface::initializeNewInterface()
            ->setDevicePHID($object->getPHID());
        }

        $new = $xaction->getNewValue();
        if ($new) {
          $interface
            ->setNetworkPHID($new['networkPHID'])
            ->setAddress($new['address'])
            ->setPort((int)$new['port']);

          if (idx($new, 'phid')) {
            $interface->setPHID($new['phid']);
          }

          $interface->save();
        } else {
          $interface->delete();
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
      case AlmanacDeviceTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Device name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        } else {
          foreach ($xactions as $xaction) {
            $message = null;
            $name = $xaction->getNewValue();

            try {
              AlmanacNames::validateName($name);
            } catch (Exception $ex) {
              $message = $ex->getMessage();
            }

            if ($message !== null) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                $message,
                $xaction);
              $errors[] = $error;
              continue;
            }

            $other = id(new AlmanacDeviceQuery())
              ->setViewer(PhabricatorUser::getOmnipotentUser())
              ->withNames(array($name))
              ->executeOne();
            if ($other && ($other->getID() != $object->getID())) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Not Unique'),
                pht('Almanac devices must have unique names.'),
                $xaction);
              $errors[] = $error;
              continue;
            }

            if ($name === $object->getName()) {
              continue;
            }

            $namespace = AlmanacNamespace::loadRestrictedNamespace(
              $this->getActor(),
              $name);
            if ($namespace) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Restricted'),
                pht(
                  'You do not have permission to create Almanac devices '.
                  'within the "%s" namespace.',
                  $namespace->getName()),
                $xaction);
              $errors[] = $error;
              continue;
            }
          }
        }

        break;
      case AlmanacDeviceTransaction::TYPE_INTERFACE:
        // We want to make sure that all the affected networks are visible to
        // the actor, any edited interfaces exist, and that the actual address
        // components are valid.

        $network_phids = array();
        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          $new = $xaction->getNewValue();
          if ($old) {
            $network_phids[] = $old['networkPHID'];
          }
          if ($new) {
            $network_phids[] = $new['networkPHID'];

            $address = $new['address'];
            if (!strlen($address)) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht('Interfaces must have an address.'),
                $xaction);
              $errors[] = $error;
            } else {
              // TODO: Validate addresses, but IPv6 addresses are not trivial
              // to validate.
            }

            $port = $new['port'];
            if (!strlen($port)) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht('Interfaces must have a port.'),
                $xaction);
              $errors[] = $error;
            } else if ((int)$port < 1 || (int)$port > 65535) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'Port numbers must be between 1 and 65535, inclusive.'),
                $xaction);
              $errors[] = $error;
            }

            $phid = idx($new, 'phid');
            if ($phid) {
              $interface_phid_type = AlmanacInterfacePHIDType::TYPECONST;
              if (phid_get_type($phid) !== $interface_phid_type) {
                $error = new PhabricatorApplicationTransactionValidationError(
                  $type,
                  pht('Invalid'),
                  pht(
                    'Precomputed interface PHIDs must be of type '.
                    'AlmanacInterfacePHIDType.'),
                  $xaction);
                $errors[] = $error;
              }
            }
          }
        }

        if ($network_phids) {
          $networks = id(new AlmanacNetworkQuery())
            ->setViewer($this->requireActor())
            ->withPHIDs($network_phids)
            ->execute();
          $networks = mpull($networks, null, 'getPHID');
        } else {
          $networks = array();
        }

        $addresses = array();
        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          if ($old) {
            $network = idx($networks, $old['networkPHID']);
            if (!$network) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'You can not edit an interface which belongs to a '.
                  'nonexistent or restricted network.'),
                $xaction);
              $errors[] = $error;
            }

            $addresses[] = $old['id'];
          }

          $new = $xaction->getNewValue();
          if ($new) {
            $network = idx($networks, $new['networkPHID']);
            if (!$network) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'You can not add an interface on a nonexistent or '.
                  'restricted network.'),
                $xaction);
              $errors[] = $error;
            }
          }
        }

        if ($addresses) {
          $interfaces = id(new AlmanacInterfaceQuery())
            ->setViewer($this->requireActor())
            ->withDevicePHIDs(array($object->getPHID()))
            ->withIDs($addresses)
            ->execute();
          $interfaces = mpull($interfaces, null, 'getID');
        } else {
          $interfaces = array();
        }

        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          if ($old) {
            $interface = idx($interfaces, $old['id']);
            if (!$interface) {
              $error = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht('You can not edit an invalid or restricted interface.'),
                $xaction);
              $errors[] = $error;
              continue;
            }

            $new = $xaction->getNewValue();
            if (!$new) {
              if ($interface->loadIsInUse()) {
                $error = new PhabricatorApplicationTransactionValidationError(
                  $type,
                  pht('In Use'),
                  pht('You can not delete an interface which is still in use.'),
                  $xaction);
                $errors[] = $error;
              }
            }
          }
        }
      break;
    }

    return $errors;
  }

}
