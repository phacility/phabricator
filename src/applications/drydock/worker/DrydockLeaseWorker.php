<?php

final class DrydockLeaseWorker extends DrydockWorker {

  protected function doWork() {
    $lease_phid = $this->getTaskDataValue('leasePHID');
    $lease = $this->loadLease($lease_phid);

    $this->activateLease($lease);
  }


  private function activateLease(DrydockLease $lease) {
    $actual_status = $lease->getStatus();

    if ($actual_status != DrydockLeaseStatus::STATUS_ACQUIRED) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Trying to activate lease from wrong status ("%s").',
          $actual_status));
    }

    $resource = $lease->getResource();
    if (!$resource) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Trying to activate lease with no resource.'));
    }

    $resource_status = $resource->getStatus();

    if ($resource_status == DrydockResourceStatus::STATUS_PENDING) {
      // TODO: This is explicitly a temporary failure -- we are waiting for
      // the resource to come up.
      throw new Exception(pht('Resource still activating.'));
    }

    if ($resource_status != DrydockResourceStatus::STATUS_ACTIVE) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Trying to activate lease on a dead resource (in status "%s").',
          $resource_status));
    }

    // NOTE: We can race resource destruction here. Between the time we
    // performed the read above and now, the resource might have closed, so
    // we may activate leases on dead resources. At least for now, this seems
    // fine: a resource dying right before we activate a lease on it should not
    // be distinguisahble from a resource dying right after we activate a lease
    // on it. We end up with an active lease on a dead resource either way, and
    // can not prevent resources dying from lightning strikes.

    $blueprint = $resource->getBlueprint();
    $blueprint->activateLease($resource, $lease);
    $this->validateActivatedLease($blueprint, $resource, $lease);
  }

  private function validateActivatedLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    if (!$lease->isActivatedLease()) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: it '.
          'returned from "%s" without activating a lease.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'acquireLease()'));
    }

  }

}
