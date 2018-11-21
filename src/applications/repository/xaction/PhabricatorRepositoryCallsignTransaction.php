<?php

final class PhabricatorRepositoryCallsignTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:callsign';

  public function generateOldValue($object) {
    return $object->getCallsign();
  }

  public function generateNewValue($object, $value) {
    if (strlen($value)) {
      return $value;
    }

    return null;
  }

  public function applyInternalEffects($object, $value) {
    $object->setCallsign($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!strlen($old)) {
      return pht(
        '%s set the callsign for this repository to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if (!strlen($new)) {
      return pht(
        '%s removed the callsign (%s) for this repository.',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s changed the callsign for this repository from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $old = $xaction->getOldValue();
      $new = $xaction->getNewValue();

      if (!strlen($new)) {
        continue;
      }

      if ($new === $old) {
        continue;
      }

      try {
        PhabricatorRepository::assertValidCallsign($new);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
        continue;
      }

      $other = id(new PhabricatorRepositoryQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withCallsigns(array($new))
        ->executeOne();
      if ($other && ($other->getID() !== $object->getID())) {
        $errors[] = $this->newError(
          pht('Duplicate'),
          pht(
            'The selected callsign ("%s") is already in use by another '.
            'repository. Choose a unique callsign.',
            $new),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
