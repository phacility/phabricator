<?php

final class PhabricatorRepositorySlugTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:slug';

  public function generateOldValue($object) {
    return $object->getRepositorySlug();
  }

  public function generateNewValue($object, $value) {
    if (strlen($value)) {
      return $value;
    }

    return null;
  }

  public function applyInternalEffects($object, $value) {
    $object->setRepositorySlug($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && !strlen($new)) {
      return pht(
        '%s removed the short name of this repository.',
        $this->renderAuthor());
    } else if (strlen($new) && !strlen($old)) {
      return pht(
        '%s set the short name of this repository to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s changed the short name of this repository from %s to %s.',
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
        PhabricatorRepository::assertValidRepositorySlug($new);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
        continue;
      }

      $other = id(new PhabricatorRepositoryQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withSlugs(array($new))
        ->executeOne();
      if ($other && ($other->getID() !== $object->getID())) {
        $errors[] = $this->newError(
          pht('Duplicate'),
          pht(
            'The selected repository short name is already in use by '.
            'another repository. Choose a unique short name.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
