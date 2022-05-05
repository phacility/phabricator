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
          array(
            'name' => 'all',
            'help' => pht('Release all resources. Dangerous!'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_all = $args->getArg('all');
    $ids = $args->getArg('id');
    if (!$ids && !$is_all) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify which resources you want to release. See "--help" for '.
          'guidance.'));
    }

    $viewer = $this->getViewer();
    $statuses = $this->getReleaseableResourceStatuses();

    $query = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withStatuses(mpull($statuses, 'getKey'));

    if ($ids) {
      $query->withIDs($ids);
    }

    $resources = $query->execute();

    if ($ids) {
      $id_map = mpull($resources, null, 'getID');

      foreach ($ids as $id) {
        $resource = idx($resources, $id);

        if (!$resource) {
          throw new PhutilArgumentUsageException(
            pht('Resource "%s" does not exist.', $id));
        }
      }

      $resources = array_select_keys($id_map, $ids);
    }

    if (!$resources) {
      echo tsprintf(
        "%s\n",
        pht('No resources selected for release.'));

      return 0;
    }

    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    PhabricatorWorker::setRunAllTasksInProcess(true);

    foreach ($resources as $resource) {
      if (!$resource->canRelease()) {
        echo tsprintf(
          "%s\n",
          pht(
            'Resource "%s" is not releasable.',
            $resource->getDisplayName()));
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
        pht(
          'Scheduled release of resource "%s".',
          $resource->getDisplayName()));
    }

    return 0;
  }

  private function getReleaseableResourceStatuses() {
    $statuses = DrydockResourceStatus::getAllStatuses();
    foreach ($statuses as $key => $status) {
      $statuses[$key] = DrydockResourceStatus::newStatusObject($status);
    }

    foreach ($statuses as $key => $status) {
      if (!$status->canRelease()) {
        unset($statuses[$key]);
      }
    }

    return $statuses;
  }
}
