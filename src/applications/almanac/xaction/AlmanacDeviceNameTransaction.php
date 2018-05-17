<?php

final class AlmanacDeviceNameTransaction
  extends AlmanacDeviceTransactionType {

  const TRANSACTIONTYPE = 'almanac:device:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s renamed this device from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Device name is required.'));
    }

    foreach ($xactions as $xaction) {
      $name = $xaction->getNewValue();

      $message = null;
      try {
        AlmanacNames::validateName($name);
      } catch (Exception $ex) {
        $message = $ex->getMessage();
      }

      if ($message !== null) {
        $errors[] = $this->newInvalidError($message, $xaction);
        continue;
      }

      if ($name === $object->getName()) {
        continue;
      }

      $other = id(new AlmanacDeviceQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withNames(array($name))
        ->executeOne();
      if ($other && ($other->getID() != $object->getID())) {
        $errors[] = $this->newInvalidError(
          pht('Almanac devices must have unique names.'),
          $xaction);
        continue;
      }

      $namespace = AlmanacNamespace::loadRestrictedNamespace(
        $this->getActor(),
        $name);
      if ($namespace) {
        $errors[] = $this->newInvalidError(
          pht(
            'You do not have permission to create Almanac devices '.
            'within the "%s" namespace.',
            $namespace->getName()),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
