<?php

final class DrydockLeaseDestroyWorker extends DrydockWorker {

  protected function doWork() {
    $lease_phid = $this->getTaskDataValue('leasePHID');
    $lease = $this->loadLease($lease_phid);
    $this->destroyLease($lease);
  }

  private function destroyLease(DrydockLease $lease) {
    $status = $lease->getStatus();

    switch ($status) {
      case DrydockLeaseStatus::STATUS_RELEASED:
      case DrydockLeaseStatus::STATUS_BROKEN:
        break;
      default:
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Unable to destroy lease ("%s"), lease has the wrong '.
            'status ("%s").',
            $lease->getPHID(),
            $status));
    }

    $resource = $lease->getResource();
    $blueprint = $resource->getBlueprint();

    $blueprint->destroyLease($resource, $lease);

    $lease
      ->setStatus(DrydockLeaseStatus::STATUS_DESTROYED)
      ->save();
  }

}
