<?php

final class DrydockResourceWorker extends DrydockWorker {

  protected function doWork() {
    $resource_phid = $this->getTaskDataValue('resourcePHID');
    $resource = $this->loadResource($resource_phid);

    $this->activateResource($resource);
  }


  private function activateResource(DrydockResource $resource) {
    $resource_status = $resource->getStatus();

    if ($resource_status != DrydockResourceStatus::STATUS_PENDING) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Trying to activate resource from wrong status ("%s").',
          $resource_status));
    }

    $blueprint = $resource->getBlueprint();
    $blueprint->activateResource($resource);
    $this->validateActivatedResource($blueprint, $resource);
  }


  private function validateActivatedResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {

    if (!$resource->isActivatedResource()) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: %s '.
          'must actually allocate the resource it returns.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'allocateResource()'));
    }

  }

}
