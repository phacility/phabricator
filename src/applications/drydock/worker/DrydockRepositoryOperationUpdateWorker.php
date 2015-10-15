<?php

final class DrydockRepositoryOperationUpdateWorker
  extends DrydockWorker {

  protected function doWork() {
    $operation_phid = $this->getTaskDataValue('operationPHID');

    $hash = PhabricatorHash::digestForIndex($operation_phid);
    $lock_key = 'drydock.operation:'.$hash;

    $lock = PhabricatorGlobalLock::newLock($lock_key)
      ->lock(1);

    try {
      $operation = $this->loadOperation($operation_phid);
      $this->handleUpdate($operation);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();
  }


  private function handleUpdate(DrydockRepositoryOperation $operation) {
    $viewer = $this->getViewer();

    $operation_state = $operation->getOperationState();

    switch ($operation_state) {
      case DrydockRepositoryOperation::STATE_WAIT:
        $operation
          ->setOperationState(DrydockRepositoryOperation::STATE_WORK)
          ->save();
        break;
      case DrydockRepositoryOperation::STATE_WORK:
        break;
      case DrydockRepositoryOperation::STATE_DONE:
      case DrydockRepositoryOperation::STATE_FAIL:
        // No more processing for these requests.
        return;
    }

    // TODO: We should probably check for other running operations with lower
    // IDs and the same repository target and yield to them here? That is,
    // enforce sequential evaluation of operations against the same target so
    // that if you land "A" and then land "B", we always finish "A" first.
    // For now, just let stuff happen in any order. We can't lease until
    // we know we're good to move forward because we might deadlock if we do:
    // we're waiting for another operation to complete, and that operation is
    // waiting for a lease we're holding.

    try {
      $lease = $this->loadWorkingCopyLease($operation);

      $interface = $lease->getInterface(
        DrydockCommandInterface::INTERFACE_TYPE);

      // No matter what happens here, destroy the lease away once we're done.
      $lease->releaseOnDestruction(true);

      $operation->getImplementation()
        ->setViewer($viewer);

      $operation->applyOperation($interface);

    } catch (PhabricatorWorkerYieldException $ex) {
      throw $ex;
    } catch (Exception $ex) {
      $operation
        ->setOperationState(DrydockRepositoryOperation::STATE_FAIL)
        ->save();
      throw $ex;
    }

    $operation
      ->setOperationState(DrydockRepositoryOperation::STATE_DONE)
      ->save();

    // TODO: Once we have sequencing, we could awaken the next operation
    // against this target after finishing or failing.
  }

  private function loadWorkingCopyLease(
    DrydockRepositoryOperation $operation) {
    $viewer = $this->getViewer();

    // TODO: This is very similar to leasing in Harbormaster, maybe we can
    // share some of the logic?

    $lease_phid = $operation->getProperty('exec.leasePHID');
    if ($lease_phid) {
      $lease = id(new DrydockLeaseQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($lease_phid))
        ->executeOne();
      if (!$lease) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Lease "%s" could not be loaded.',
            $lease_phid));
      }
    } else {
      $working_copy_type = id(new DrydockWorkingCopyBlueprintImplementation())
        ->getType();

      $repository = $operation->getRepository();

      $allowed_phids = $repository->getAutomationBlueprintPHIDs();
      $authorizing_phid = $repository->getPHID();

      $lease = DrydockLease::initializeNewLease()
        ->setResourceType($working_copy_type)
        ->setOwnerPHID($operation->getPHID())
        ->setAuthorizingPHID($authorizing_phid)
        ->setAllowedBlueprintPHIDs($allowed_phids);

      $map = $this->buildRepositoryMap($operation);

      $lease->setAttribute('repositories.map', $map);

      $task_id = $this->getCurrentWorkerTaskID();
      if ($task_id) {
        $lease->setAwakenTaskIDs(array($task_id));
      }

      $operation
        ->setProperty('exec.leasePHID', $lease->getPHID())
        ->save();

      $lease->queueForActivation();
    }

    if ($lease->isActivating()) {
      throw new PhabricatorWorkerYieldException(15);
    }

    if (!$lease->isActive()) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Lease "%s" never activated.',
          $lease->getPHID()));
    }

    return $lease;
  }

  private function buildRepositoryMap(DrydockRepositoryOperation $operation) {
    $repository = $operation->getRepository();

    $target = $operation->getRepositoryTarget();
    list($type, $name) = explode(':', $target, 2);
    switch ($type) {
      case 'branch':
        $spec = array(
          'branch' => $name,
        );
        break;
      default:
        throw new Exception(
          pht(
            'Unknown repository operation target type "%s" (in target "%s").',
            $type,
            $target));
    }

    $map = array();
    $map[$repository->getCloneName()] = array(
      'phid' => $repository->getPHID(),
      'default' => true,
    ) + $spec;

    return $map;
  }
}
