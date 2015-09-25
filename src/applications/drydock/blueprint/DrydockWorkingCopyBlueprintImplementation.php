<?php

final class DrydockWorkingCopyBlueprintImplementation
  extends DrydockBlueprintImplementation {

  public function isEnabled() {
    return true;
  }

  public function getBlueprintName() {
    return pht('Working Copy');
  }

  public function getDescription() {
    return pht('Allows Drydock to check out working copies of repositories.');
  }

  public function canAnyBlueprintEverAllocateResourceForLease(
    DrydockLease $lease) {
    return true;
  }

  public function canEverAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    return true;
  }

  public function canAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    return true;
  }

  public function canAcquireLeaseOnResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    $have_phid = $resource->getAttribute('repositoryPHID');
    $need_phid = $lease->getAttribute('repositoryPHID');

    if ($need_phid !== $have_phid) {
      return false;
    }

    if (!DrydockSlotLock::isLockFree($this->getLeaseSlotLock($resource))) {
      return false;
    }

    return true;
  }

  public function acquireLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    $lease
      ->needSlotLock($this->getLeaseSlotLock($resource))
      ->acquireOnResource($resource);
  }

  private function getLeaseSlotLock(DrydockResource $resource) {
    $resource_phid = $resource->getPHID();
    return "workingcopy.lease({$resource_phid})";
  }

  public function allocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {

    $repository_phid = $lease->getAttribute('repositoryPHID');
    $repository = $this->loadRepository($repository_phid);

    $resource = $this->newResourceTemplate(
      $blueprint,
      pht(
        'Working Copy (%s)',
        $repository->getCallsign()));

    $resource_phid = $resource->getPHID();

    $host_lease = $this->newLease($blueprint)
      ->setResourceType('host')
      ->setOwnerPHID($resource_phid)
      ->setAttribute('workingcopy.resourcePHID', $resource_phid)
      ->queueForActivation();

    // TODO: Add some limits to the number of working copies we can have at
    // once?

    return $resource
      ->setAttribute('repositoryPHID', $repository->getPHID())
      ->setAttribute('host.leasePHID', $host_lease->getPHID())
      ->allocateResource();
  }

  public function activateResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {

    $lease = $this->loadHostLease($resource);
    $this->requireActiveLease($lease);

    $repository_phid = $resource->getAttribute('repositoryPHID');
    $repository = $this->loadRepository($repository_phid);
    $repository_id = $repository->getID();

    $command_type = DrydockCommandInterface::INTERFACE_TYPE;
    $interface = $lease->getInterface($command_type);

    // TODO: Make this configurable.
    $resource_id = $resource->getID();
    $root = "/var/drydock/workingcopy-{$resource_id}";
    $path = "{$root}/repo/{$repository_id}/";

    $interface->execx(
      'git clone -- %s %s',
      (string)$repository->getCloneURIObject(),
      $path);

    $resource
      ->setAttribute('workingcopy.root', $root)
      ->setAttribute('workingcopy.path', $path)
      ->activateResource();
  }

  public function destroyResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {

    $lease = $this->loadHostLease($resource);

    // Destroy the lease on the host.
    $lease->releaseOnDestruction();

    // Destroy the working copy on disk.
    $command_type = DrydockCommandInterface::INTERFACE_TYPE;
    $interface = $lease->getInterface($command_type);

    $root_key = 'workingcopy.root';
    $root = $resource->getAttribute($root_key);
    if (strlen($root)) {
      $interface->execx('rm -rf -- %s', $root);
    }
  }

  public function activateLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    $command_type = DrydockCommandInterface::INTERFACE_TYPE;
    $interface = $lease->getInterface($command_type);

    $cmd = array();
    $arg = array();

    $cmd[] = 'git clean -d --force';
    $cmd[] = 'git reset --hard HEAD';
    $cmd[] = 'git fetch';

    $commit = $lease->getAttribute('commit');
    $branch = $lease->getAttribute('branch');

    if ($commit !== null) {
      $cmd[] = 'git reset --hard %s';
      $arg[] = $commit;
    } else if ($branch !== null) {
      $cmd[] = 'git reset --hard %s';
      $arg[] = $branch;
    }

    $cmd = implode(' && ', $cmd);
    $argv = array_merge(array($cmd), $arg);

    $result = call_user_func_array(
      array($interface, 'execx'),
      $argv);

    $lease->activateOnResource($resource);
  }

  public function didReleaseLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    // We leave working copies around even if there are no leases on them,
    // since the cost to maintain them is nearly zero but rebuilding them is
    // moderately expensive and it's likely that they'll be reused.
    return;
  }

  public function destroyLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    // When we activate a lease we just reset the working copy state and do
    // not create any new state, so we don't need to do anything special when
    // destroying a lease.
    return;
  }

  public function getType() {
    return 'working-copy';
  }

  public function getInterface(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    switch ($type) {
      case DrydockCommandInterface::INTERFACE_TYPE:
        $host_lease = $this->loadHostLease($resource);
        $command_interface = $host_lease->getInterface($type);

        $path = $resource->getAttribute('workingcopy.path');
        $command_interface->setWorkingDirectory($path);

        return $command_interface;
    }
  }

  private function loadRepository($repository_phid) {
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($repository_phid))
      ->executeOne();
    if (!$repository) {
      // TODO: Permanent failure.
      throw new Exception(
        pht(
          'Repository PHID "%s" does not exist.',
          $repository_phid));
    }

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      default:
        // TODO: Permanent failure.
        throw new Exception(pht('Unsupported VCS!'));
    }

    return $repository;
  }

  private function loadHostLease(DrydockResource $resource) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $lease_phid = $resource->getAttribute('host.leasePHID');

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($lease_phid))
      ->executeOne();
    if (!$lease) {
      // TODO: Permanent failure.
      throw new Exception(pht('Unable to load lease "%s".', $lease_phid));
    }

    return $lease;
  }


}
