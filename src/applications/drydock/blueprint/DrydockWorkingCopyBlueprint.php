<?php

final class DrydockWorkingCopyBlueprint extends DrydockBlueprint {

  public function isEnabled() {
    return PhabricatorEnv::getEnvConfig('drydock.localhost.enabled');
  }

  protected function executeAllocateResource(DrydockLease $lease) {
    $repository_id = $lease->getAttribute('repositoryID');
    if (!$repository_id) {
      throw new Exception(
        "Lease is missing required 'repositoryID' attribute.");
    }

    $repository = id(new PhabricatorRepository())->load($repository_id);

    if (!$repository) {
      throw new Exception(
        "Repository '{$repository_id}' does not exist!");
    }

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    $host_lease = id(new DrydockLease())
      ->setResourceType('host')
      ->waitUntilActive();

    $path = $host_lease->getAttribute('path').'/'.$repository->getCallsign();

    $cmd = $host_lease->getInterface('command');
    $cmd->execx(
      'git clone --origin origin %s %s',
      $repository->getRemoteURI(),
      $path);

    $resource = $this->newResourceTemplate($repository->getCallsign());
    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    $resource->setAttribute('lease.host', $host_lease->getID());
    $resource->setAttribute('path', $path);
    $resource->save();

    return $resource;
  }

  protected function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {
    return;
  }

  public function getType() {
    return 'working-copy';
  }

  public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    throw new Exception("No interface of type '{$type}'.");
  }

}
