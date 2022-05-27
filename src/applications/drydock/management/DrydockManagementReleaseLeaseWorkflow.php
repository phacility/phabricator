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
          array(
            'name' => 'all',
            'help' => pht('Release all leases. Dangerous!'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_all = $args->getArg('all');
    $ids = $args->getArg('id');

    if (!$ids && !$is_all) {
      throw new PhutilArgumentUsageException(
        pht(
          'Select which leases you want to release. See "--help" for '.
          'guidance.'));
    }

    $viewer = $this->getViewer();

    $statuses = $this->getReleaseableLeaseStatuses();

    $query = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withStatuses(mpull($statuses, 'getKey'));

    if ($ids) {
      $query->withIDs($ids);
    }

    $leases = $query->execute();

    if ($ids) {
      $id_map = mpull($leases, null, 'getID');

      foreach ($ids as $id) {
        $lease = idx($id_map, $id);
        if (!$lease) {
          throw new PhutilArgumentUsageException(
            pht('Lease "%s" does not exist.', $id));
        }
      }

      $leases = array_select_keys($id_map, $ids);
    }

    if (!$leases) {
      echo tsprintf(
        "%s\n",
        pht('No leases selected for release.'));

      return 0;
    }

    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    PhabricatorWorker::setRunAllTasksInProcess(true);

    foreach ($leases as $lease) {
      if (!$lease->canRelease()) {
        echo tsprintf(
          "%s\n",
          pht(
            'Lease "%s" is not releasable.',
            $lease->getDisplayName()));
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
        pht(
          'Scheduled release of lease "%s".',
          $lease->getDisplayName()));
    }

  }

  private function getReleaseableLeaseStatuses() {
    $statuses = DrydockLeaseStatus::getAllStatuses();
    foreach ($statuses as $key => $status) {
      $statuses[$key] = DrydockLeaseStatus::newStatusObject($status);
    }

    foreach ($statuses as $key => $status) {
      if (!$status->canRelease()) {
        unset($statuses[$key]);
      }
    }

    return $statuses;
  }

}
