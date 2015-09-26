<?php

final class DrydockResourceDestroyWorker extends DrydockWorker {

  protected function doWork() {
    $resource_phid = $this->getTaskDataValue('resourcePHID');
    $resource = $this->loadResource($resource_phid);
    $this->destroyResource($resource);
  }

  private function destroyResource(DrydockResource $resource) {
    $status = $resource->getStatus();

    switch ($status) {
      case DrydockResourceStatus::STATUS_RELEASED:
      case DrydockResourceStatus::STATUS_BROKEN:
        break;
      default:
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Unable to destroy resource ("%s"), resource has the wrong '.
            'status ("%s").',
            $resource->getPHID(),
            $status));
    }

    $blueprint = $resource->getBlueprint();
    $blueprint->destroyResource($resource);

    $resource
      ->setStatus(DrydockResourceStatus::STATUS_DESTROYED)
      ->save();
  }

}
