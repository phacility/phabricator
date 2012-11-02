<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
