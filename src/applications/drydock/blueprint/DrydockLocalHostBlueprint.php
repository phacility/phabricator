<?php

final class DrydockLocalHostBlueprint extends DrydockBlueprint {

  public function isEnabled() {
    return PhabricatorEnv::getEnvConfig('drydock.localhost.enabled');
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
    $path = PhabricatorEnv::getEnvConfig('drydock.localhost.path');
    if (!Filesystem::pathExists($path)) {
      throw new Exception(
        "Path '{$path}' does not exist!");
    }
    Filesystem::assertIsDirectory($path);
    Filesystem::assertWritable($path);

    $resource = $this->newResourceTemplate('localhost');
    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    $resource->save();

    return $resource;
  }

  protected function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {
    return;
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
