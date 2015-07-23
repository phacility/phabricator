<?php

final class DrydockPreallocatedHostBlueprintImplementation
  extends DrydockBlueprintImplementation {

  public function isEnabled() {
    return true;
  }

  public function getBlueprintName() {
    return pht('Preallocated Remote Hosts');
  }

  public function getDescription() {
    return pht('Allows Drydock to run on specific remote hosts you configure.');
  }

  public function canAllocateMoreResources(array $pool) {
    return false;
  }

  protected function executeAllocateResource(DrydockLease $lease) {
    throw new Exception(
      pht("Preallocated hosts can't be dynamically allocated."));
  }

  protected function canAllocateLease(
    DrydockResource $resource,
    DrydockLease $lease) {
    return
      $lease->getAttribute('platform') === $resource->getAttribute('platform');
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

    // Because preallocated resources are manually created, we should verify
    // we have all the information we need.
    PhutilTypeSpec::checkMap(
      $resource->getAttributesForTypeSpec(
        array('platform', 'host', 'port', 'credential', 'path')),
      array(
        'platform' => 'string',
        'host' => 'string',
        'port' => 'string', // Value is a string from the command line
        'credential' => 'string',
        'path' => 'string',
      ));
    $v_platform = $resource->getAttribute('platform');
    $v_path = $resource->getAttribute('path');

    // Similar to DrydockLocalHostBlueprint, we create a folder
    // on the remote host that the lease can use.

    $lease_id = $lease->getID();

    // Can't use DIRECTORY_SEPERATOR here because that is relevant to
    // the platform we're currently running on, not the platform we are
    // remoting to.
    $separator = '/';
    if ($v_platform === 'windows') {
      $separator = '\\';
    }

    // Clean up the directory path a little.
    $base_path = rtrim($v_path, '/');
    $base_path = rtrim($base_path, '\\');
    $full_path = $base_path.$separator.$lease_id;

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
        return id(new DrydockSSHCommandInterface())
          ->setConfiguration(array(
            'host' => $resource->getAttribute('host'),
            'port' => $resource->getAttribute('port'),
            'credential' => $resource->getAttribute('credential'),
            'platform' => $resource->getAttribute('platform'),
          ))
          ->setWorkingDirectory($lease->getAttribute('path'));
      case 'filesystem':
        return id(new DrydockSFTPFilesystemInterface())
          ->setConfiguration(array(
            'host' => $resource->getAttribute('host'),
            'port' => $resource->getAttribute('port'),
            'credential' => $resource->getAttribute('credential'),
          ));
    }

    throw new Exception(pht("No interface of type '%s'.", $type));
  }

}
