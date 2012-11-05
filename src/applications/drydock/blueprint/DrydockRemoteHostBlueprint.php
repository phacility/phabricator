<?php

/**
 * TODO: Is this concrete-extensible?
 */
class DrydockRemoteHostBlueprint extends DrydockBlueprint {

  public function getType() {
    return 'host';
  }

  public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    switch ($type) {
      case 'command':
        $ssh = new DrydockSSHCommandInterface();
        $ssh->setConfiguration(
          array(
            'host'        => 'secure.phabricator.com',
            'user'        => 'ec2-user',
            'ssh-keyfile' => '/Users/epriestley/.ssh/id_ec2w',
          ));
        return $ssh;
    }

    throw new Exception("No interface of type '{$type}'.");
  }

}
