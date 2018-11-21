<?php

final class PhabricatorRepositoryStagingURITransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:staging-uri';

  public function generateOldValue($object) {
    return $object->getDetail('staging-uri');
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('staging-uri', $value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!strlen($old)) {
      return pht(
        '%s set %s as the staging area for this repository.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if (!strlen($new)) {
      return pht(
        '%s removed %s as the staging area for this repository.',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s changed the staging area for this repository from '.
        '%s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $old = $this->generateOldValue($object);
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if (!strlen($new)) {
        continue;
      }

      if ($new === $old) {
        continue;
      }

      try {
        PhabricatorRepository::assertValidRemoteURI($new);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
