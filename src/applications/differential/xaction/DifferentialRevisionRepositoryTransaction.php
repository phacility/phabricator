<?php

final class DifferentialRevisionRepositoryTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.repository';

  public function generateOldValue($object) {
    return $object->getRepositoryPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRepositoryPHID($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    if ($old && $new) {
      return pht(
        '%s changed the repository for this revision from %s to %s.',
        $this->renderAuthor(),
        $this->renderHandle($old),
        $this->renderHandle($new));
    } else if ($new) {
      return pht(
        '%s set the repository for this revision to %s.',
        $this->renderAuthor(),
        $this->renderHandle($new));
    } else {
      return pht(
        '%s removed %s as the repository for this revision.',
        $this->renderAuthor(),
        $this->renderHandle($old));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    if ($old && $new) {
      return pht(
        '%s changed the repository for %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderHandle($old),
        $this->renderHandle($new));
    } else if ($new) {
      return pht(
        '%s set the repository for %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderHandle($new));
    } else {
      return pht(
        '%s removed %s as the repository for %s.',
        $this->renderAuthor(),
        $this->renderHandle($old),
        $this->renderObject());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();

    $errors = array();

    $old_value = $object->getRepositoryPHID();
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      if (!$new_value) {
        continue;
      }

      if ($new_value == $old_value) {
        continue;
      }

      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($actor)
        ->withPHIDs(array($new_value))
        ->executeOne();
      if (!$repository) {
        $errors[] = $this->newInvalidError(
          pht(
            'Repository "%s" is not a valid repository, or you do not have '.
            'permission to view it.',
            $new_value),
          $xaction);
      }
    }

    return $errors;
  }

}
