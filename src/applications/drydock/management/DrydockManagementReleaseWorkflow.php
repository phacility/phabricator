<?php

final class DrydockManagementReleaseWorkflow
  extends DrydockManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('release')
      ->setSynopsis('Release a lease.')
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
        "Specify one or more lease IDs to release.");
    }

    $viewer = $this->getViewer();

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    foreach ($ids as $id) {
      $lease = idx($leases, $id);
      if (!$lease) {
        $console->writeErr("Lease %d does not exist!\n", $id);
      } else if ($lease->getStatus() != DrydockLeaseStatus::STATUS_ACTIVE) {
        $console->writeErr("Lease %d is not 'active'!\n", $id);
      } else {
        $resource = $lease->getResource();
        $blueprint = $resource->getBlueprint();
        $blueprint->releaseLease($resource, $lease);

        $console->writeErr("Released lease %d.\n", $id);
      }
    }

  }

}
