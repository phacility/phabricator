<?php

final class PhabricatorRepositoryActivateTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:activate';

  public function generateOldValue($object) {
    return $object->isTracked();
  }

  public function applyInternalEffects($object, $value) {
    // The first time a repository is activated, clear the "new repository"
    // flag so we stop showing setup hints.
    if ($value) {
      $object->setDetail('newly-initialized', false);
    }

    $object->setDetail('tracking-enabled', $value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    // TODO: Old versions of this transaction use a boolean value, but
    // should be migrated.
    $is_deactivate =
      (!$new) ||
      ($new == PhabricatorRepository::STATUS_INACTIVE);

    if (!$is_deactivate) {
      return pht(
        '%s activated this repository.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s deactivated this repository.',
        $this->renderAuthor());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $status_map = PhabricatorRepository::getStatusMap();
    foreach ($xactions as $xaction) {
      $status = $xaction->getNewValue();
      if (empty($status_map[$status])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Repository status "%s" is not valid. Valid statuses are: %s.',
            $status,
            implode(', ', array_keys($status_map))),
          $xaction);
      }
    }

    return $errors;
  }

}
