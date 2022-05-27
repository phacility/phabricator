<?php

final class PhabricatorRepositoryVCSTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:vcs';

  public function generateOldValue($object) {
    return $object->getVersionControlSystem();
  }

  public function applyInternalEffects($object, $value) {
    $object->setVersionControlSystem($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $vcs_map = PhabricatorRepositoryType::getAllRepositoryTypes();
    $current_vcs = $object->getVersionControlSystem();

    if (!$this->isNewObject()) {
      foreach ($xactions as $xaction) {
        if ($xaction->getNewValue() == $current_vcs) {
          continue;
        }

        $errors[] = $this->newInvalidError(
          pht(
            'You can not change the version control system an existing '.
            'repository uses. It can only be set when a repository is '.
            'first created.'),
          $xaction);
      }

      return $errors;
    }

    $value = $object->getVersionControlSystem();

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();

      if (isset($vcs_map[$value])) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'Specified version control system must be a VCS '.
          'recognized by this software. Valid systems are: %s.',
          implode(', ', array_keys($vcs_map))),
        $xaction);
    }

    if ($value === null) {
      $errors[] = $this->newRequiredError(
        pht(
          'When creating a repository, you must specify a valid '.
          'underlying version control system. Valid systems are: %s.',
          implode(', ', array_keys($vcs_map))));
    }

    return $errors;
  }
}
