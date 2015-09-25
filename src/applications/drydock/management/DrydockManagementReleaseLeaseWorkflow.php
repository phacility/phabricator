<?php

final class DrydockManagementReleaseLeaseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('release-lease')
      ->setSynopsis(pht('Release a lease.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'repeat' => true,
            'help' => pht('Lease ID to release.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more lease IDs to release with "%s".',
          '--id'));
    }

    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    PhabricatorWorker::setRunAllTasksInProcess(true);
    foreach ($ids as $id) {
      $lease = idx($leases, $id);
      if (!$lease) {
        echo tsprintf(
          "%s\n",
          pht('Lease "%s" does not exist.', $id));
        continue;
      }

      if (!$lease->canRelease()) {
        echo tsprintf(
          "%s\n",
          pht('Lease "%s" is not releasable.', $id));
        continue;
      }

      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($lease->getPHID())
        ->setAuthorPHID($drydock_phid)
        ->setCommand(DrydockCommand::COMMAND_RELEASE)
        ->save();

      $lease->scheduleUpdate();

      echo tsprintf(
        "%s\n",
        pht('Scheduled release of lease "%s".', $id));
    }

  }

}
