<?php

final class DrydockManagementReleaseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('release')
      ->setSynopsis(pht('Release a lease.'))
      ->setArguments(
        array(
          array(
            'name'      => 'ids',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $ids = $args->getArg('ids');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more lease IDs to release.'));
    }

    $viewer = $this->getViewer();

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    foreach ($ids as $id) {
      $lease = idx($leases, $id);
      if (!$lease) {
        $console->writeErr("%s\n", pht('Lease %d does not exist!', $id));
      } else if ($lease->getStatus() != DrydockLeaseStatus::STATUS_ACTIVE) {
        $console->writeErr("%s\n", pht("Lease %d is not 'active'!", $id));
      } else {
        $resource = $lease->getResource();
        $blueprint = $resource->getBlueprint();
        $blueprint->releaseLease($resource, $lease);

        $console->writeErr("%s\n", pht('Released lease %d.', $id));
      }
    }

  }

}
