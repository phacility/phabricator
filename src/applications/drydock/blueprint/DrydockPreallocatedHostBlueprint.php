<?php

final class DrydockPreallocatedHostBlueprint extends DrydockBlueprint {

  public function isEnabled() {
    return true;
  }

  public function canAllocateMoreResources(array $pool) {
    return false;
  }

  protected function executeAllocateResource(DrydockLease $lease) {
    throw new Exception("Preallocated hosts can't be dynamically allocated.");
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

    // Similar to DrydockLocalHostBlueprint, we create a folder
    // on the remote host that the lease can use.

    $lease_id = $lease->getID();

    // Can't use DIRECTORY_SEPERATOR here because that is relevant to
    // the platform we're currently running on, not the platform we are
    // remoting to.
    $separator = '/';
    if ($lease->getAttribute('platform') === 'windows') {
      $separator = '\\';
    }

    // Clean up the directory path a little.
    $base_path = rtrim($resource->getAttribute('path'), '/');
    $base_path = rtrim($base_path, '\\');
    $full_path = $base_path.$separator.$lease_id;

    $cmd = $lease->getInterface('command');

    if ($lease->getAttribute('platform') !== 'windows') {
      $cmd->execx('mkdir %s', $full_path);
    } else {
      // Windows is terrible.  The mkdir command doesn't even support putting
      // the path in quotes.  IN QUOTES.  ARGUHRGHUGHHGG!!  Do some terribly
      // inaccurate sanity checking since we can't safely escape the path.
      if (preg_match('/^[A-Z]\\:\\\\[a-zA-Z0-9\\\\\\ ]/', $full_path) === 0) {
        throw new Exception(
          'Unsafe path detected for Windows platform: "'.$full_path.'".');
      }
      $cmd->execx('mkdir %C', $full_path);
    }

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
            'user' => $resource->getAttribute('user'),
            'ssh-keyfile' => $resource->getAttribute('ssh-keyfile'),
            'platform' => $resource->getAttribute('platform')));
    }

    throw new Exception("No interface of type '{$type}'.");
  }

}
