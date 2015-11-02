<?php

final class DrydockManagementReleaseResourceWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('release-resource')
      ->setSynopsis(pht('Release a resource.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'repeat' => true,
            'help' => pht('Resource ID to release.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more resource IDs to release with "%s".',
          '--id'));
    }

    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    PhabricatorWorker::setRunAllTasksInProcess(true);
    foreach ($ids as $id) {
      $resource = idx($resources, $id);

      if (!$resource) {
        echo tsprintf(
          "%s\n",
          pht('Resource "%s" does not exist.', $id));
        continue;
      }

      if (!$resource->canRelease()) {
        echo tsprintf(
          "%s\n",
          pht('Resource "%s" is not releasable.', $id));
        continue;
      }

      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($resource->getPHID())
        ->setAuthorPHID($drydock_phid)
        ->setCommand(DrydockCommand::COMMAND_RELEASE)
        ->save();

      $resource->scheduleUpdate();

      echo tsprintf(
        "%s\n",
        pht('Scheduled release of resource "%s".', $id));
    }

  }

}
