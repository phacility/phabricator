<?php

final class AlmanacDeviceEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Almanac Device');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = AlmanacDeviceTransaction::TYPE_NAME;

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
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacDeviceTransaction::TYPE_NAME:
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
    }

    return $errors;
  }

}
