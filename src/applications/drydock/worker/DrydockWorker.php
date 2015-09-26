<?php

abstract class DrydockWorker extends PhabricatorWorker {

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function loadLease($lease_phid) {
    $viewer = $this->getViewer();

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($lease_phid))
      ->executeOne();
    if (!$lease) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such lease "%s"!', $lease_phid));
    }

    return $lease;
  }

  protected function loadResource($resource_phid) {
    $viewer = $this->getViewer();

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($resource_phid))
      ->executeOne();
    if (!$resource) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such resource "%s"!', $resource_phid));
    }

    return $resource;
  }

  protected function loadCommands($target_phid) {
    $viewer = $this->getViewer();

    $commands = id(new DrydockCommandQuery())
      ->setViewer($viewer)
      ->withTargetPHIDs(array($target_phid))
      ->withConsumed(false)
      ->execute();

    $commands = msort($commands, 'getID');

    return $commands;
  }

}
