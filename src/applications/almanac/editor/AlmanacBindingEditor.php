<?php

final class AlmanacBindingEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Almanac Binding');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = AlmanacBindingTransaction::TYPE_INTERFACE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        return $object->getInterfacePHID();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
        return $xaction->getNewValue();
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
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacBindingTransaction::TYPE_INTERFACE:
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
