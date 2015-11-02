<?php

final class DrydockTestRepositoryOperation
  extends DrydockRepositoryOperationType {

  const OPCONST = 'test';

  public function getOperationDescription(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer) {
    return pht('Test Configuration');
  }

  public function getOperationCurrentStatus(
    DrydockRepositoryOperation $operation,
    PhabricatorUser $viewer) {

    $repository = $operation->getRepository();
    switch ($operation->getOperationState()) {
      case DrydockRepositoryOperation::STATE_WAIT:
        return pht(
          'Waiting to test configuration for %s...',
          $repository->getMonogram());
      case DrydockRepositoryOperation::STATE_WORK:
        return pht(
          'Testing configuration for %s. This may take a moment if Drydock '.
          'has to clone the repository for the first time.',
          $repository->getMonogram());
      case DrydockRepositoryOperation::STATE_DONE:
        return pht(
          'Success! Automation is configured properly and Drydock can '.
          'operate on %s.',
          $repository->getMonogram());
    }
  }

  public function applyOperation(
    DrydockRepositoryOperation $operation,
    DrydockInterface $interface) {
    $repository = $operation->getRepository();

    if ($repository->isGit()) {
      $interface->execx('git status');
    } else if ($repository->isHg()) {
      $interface->execx('hg status');
    } else if ($repository->isSVN()) {
      $interface->execx('svn status');
    } else {
      throw new PhutilMethodNotImplementedException();
    }
  }

}
