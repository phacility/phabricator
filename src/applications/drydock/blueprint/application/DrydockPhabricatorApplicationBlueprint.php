<?php

abstract class DrydockPhabricatorApplicationBlueprint
  extends DrydockBlueprint {

  public function getType() {
    return 'application';
  }

  public function canAllocateResources() {
    return true;
  }

  public function executeAllocateResource(DrydockLease $lease) {

    $resource = $this->newResourceTemplate('Phabricator');

    $resource->setStatus(DrydockResourceStatus::STATUS_ALLOCATING);
    $resource->save();

    $host = id(new DrydockLease())
      ->setResourceType('host')
      ->queueForActivation();

    $cmd = $host->waitUntilActive()->getInterface('command');

    $cmd->execx(<<<EOINSTALL
yum install git &&
mkdir -p /opt/drydock &&
cd /opt/drydock &&
git clone git://github.com/facebook/libphutil.git &&
git clone git://github.com/facebook/arcanist.git &&
git clone git://github.com/facebook/phabricator.git
EOINSTALL
      );

    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    $resource->save();

    return $resource;
  }

  public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {


    throw new Exception("No interface of type '{$type}'.");
  }

}
