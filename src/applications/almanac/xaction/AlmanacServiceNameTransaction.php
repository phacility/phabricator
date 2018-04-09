<?php

final class AlmanacServiceNameTransaction
  extends AlmanacServiceTransactionType {

  const TRANSACTIONTYPE = 'almanac:service:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s renamed this service from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s renamed %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Almanac services must have a name.'));
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

      $other = id(new AlmanacServiceQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withNames(array($name))
        ->executeOne();
      if ($other && ($other->getID() != $object->getID())) {
        $errors[] = $this->newInvalidError(
          pht('Almanac services must have unique names.'),
          $xaction);
        continue;
      }

      $namespace = AlmanacNamespace::loadRestrictedNamespace(
        $this->getActor(),
        $name);
      if ($namespace) {
        $errors[] = $this->newInvalidError(
          pht(
            'You do not have permission to create Almanac services '.
            'within the "%s" namespace.',
            $namespace->getName()),
          $xaction);
        continue;
      }
    }

    return $errors;
  }
}
