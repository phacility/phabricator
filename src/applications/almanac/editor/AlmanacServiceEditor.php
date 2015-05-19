<?php

final class AlmanacServiceEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Almanac Service');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = AlmanacServiceTransaction::TYPE_NAME;
    $types[] = AlmanacServiceTransaction::TYPE_LOCK;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case AlmanacServiceTransaction::TYPE_NAME:
        return $object->getName();
      case AlmanacServiceTransaction::TYPE_LOCK:
        return (bool)$object->getIsLocked();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacServiceTransaction::TYPE_NAME:
        return $xaction->getNewValue();
      case AlmanacServiceTransaction::TYPE_LOCK:
        return (bool)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacServiceTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case AlmanacServiceTransaction::TYPE_LOCK:
        $object->setIsLocked((int)$xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacServiceTransaction::TYPE_NAME:
        return;
      case AlmanacServiceTransaction::TYPE_LOCK:
        $service = id(new AlmanacServiceQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs(array($object->getPHID()))
          ->needBindings(true)
          ->executeOne();

        $devices = array();
        foreach ($service->getBindings() as $binding) {
          $device = $binding->getInterface()->getDevice();
          $devices[$device->getPHID()] = $device;
        }

        foreach ($devices as $device) {
          $device->rebuildDeviceLocks();
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
      case AlmanacServiceTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Service name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        } else {
          foreach ($xactions as $xaction) {
            $message = null;

            $name = $xaction->getNewValue();

            try {
              AlmanacNames::validateServiceOrDeviceName($name);
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
            }
          }
        }

        if ($xactions) {
          $duplicate = id(new AlmanacServiceQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withNames(array(last($xactions)->getNewValue()))
            ->executeOne();
          if ($duplicate && ($duplicate->getID() != $object->getID())) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Not Unique'),
              pht('Almanac services must have unique names.'),
              last($xactions));
            $errors[] = $error;
          }
        }

        break;
    }

    return $errors;
  }



}
