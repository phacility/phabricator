<?php

final class AlmanacNetworkNameTransaction
  extends AlmanacNetworkTransactionType {

  const TRANSACTIONTYPE = 'almanac:network:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s renamed this network from %s to %s.',
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
        pht('Network name is required.'));
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

      $other = id(new AlmanacNetworkQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withNames(array($name))
        ->executeOne();
      if ($other && ($other->getID() != $object->getID())) {
        $errors[] = $this->newInvalidError(
          pht('Almanac networks must have unique names.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
