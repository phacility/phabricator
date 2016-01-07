<?php

final class DrydockManagementReclaimWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('reclaim')
      ->setSynopsis(pht('Reclaim unused resources.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    PhabricatorWorker::setRunAllTasksInProcess(true);

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withStatuses(
        array(
          DrydockResourceStatus::STATUS_ACTIVE,
        ))
      ->execute();
    foreach ($resources as $resource) {
      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($resource->getPHID())
        ->setAuthorPHID($drydock_phid)
        ->setCommand(DrydockCommand::COMMAND_RECLAIM)
        ->save();

      $resource->scheduleUpdate();

      $resource = $resource->reload();

      $name = pht(
        'Resource %d: %s',
        $resource->getID(),
        $resource->getResourceName());

      switch ($resource->getStatus()) {
        case DrydockResourceStatus::STATUS_RELEASED:
        case DrydockResourceStatus::STATUS_DESTROYED:
          echo tsprintf(
            "%s\n",
            pht(
              'Resource "%s" was reclaimed.',
              $name));
          break;
        default:
          echo tsprintf(
            "%s\n",
            pht(
              'Resource "%s" could not be reclaimed.',
              $name));
          break;
      }
    }

  }

}
