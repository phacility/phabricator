<?php

final class DrydockLocalHostBlueprint extends DrydockBlueprint {

  public function isEnabled() {
    // TODO: Figure this out.
    return true;
  }

  public function canAllocateMoreResources(array $pool) {
    assert_instances_of($pool, 'DrydockResource');

    // The localhost can be allocated only once.
    foreach ($pool as $resource) {
      if ($resource->getBlueprintClass() == $this->getBlueprintClass()) {
        return false;
      }
    }

    return true;
  }

  protected function executeAllocateResource(DrydockLease $lease) {
    // TODO: Don't hard-code this.
    $path = '/var/drydock/';

    if (!Filesystem::pathExists($path)) {
      throw new Exception(
        "Path '{$path}' does not exist!");
    }
    Filesystem::assertIsDirectory($path);
    Filesystem::assertWritable($path);

    $resource = $this->newResourceTemplate('Host (localhost)');
    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    $resource->setAttribute('path', $path);
    $resource->save();

    return $resource;
  }

  protected function canAllocateLease(
    DrydockResource $resource,
    DrydockLease $lease) {
    return true;
  }

  protected function shouldAllocateLease(
    DrydockResource $resource,
    DrydockLease $lease,
    array $other_leases) {
    return true;
  }

  protected function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {

    $lease_id = $lease->getID();

    $full_path = $resource->getAttribute('path').$lease_id.'/';

    $cmd = $lease->getInterface('command');
    $cmd->execx('mkdir %s', $full_path);

    $lease->setAttribute('path', $full_path);
  }

  public function getType() {
    return 'host';
  }

  public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    switch ($type) {
      case 'command':
        return new DrydockLocalCommandInterface();
    }

    throw new Exception("No interface of type '{$type}'.");
  }

}
