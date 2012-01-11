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

class DrydockEC2HostBlueprint extends DrydockRemoteHostBlueprint {

  public function canAllocateResources() {
    return true;
  }

  public function allocateResource() {

    echo "ALLOCATING EC2 HOST!\n";

    $resource = new DrydockResource();
    $resource->setBlueprintClass(get_class($this));
    $resource->setType($this->getType());
    $resource->setStatus(DrydockResourceStatus::STATUS_PENDING);
    $resource->setName('EC2 Host');
    $resource->save();

    $resource->setStatus(DrydockResourceStatus::STATUS_ALLOCATING);
    $resource->save();

    $xml = $this->executeEC2Query(
      'RunInstances',
      array(
        'ImageId'             => 'ami-c7c99982',
        'MinCount'            => 1,
        'MaxCount'            => 1,
        'KeyName'             => 'ec2wc',
        'SecurityGroupId.1'   => 'sg-6bffff2e',
        'InstanceType'        => 't1.micro',
      ));

    $instance_id = (string)$xml->instancesSet[0]->item[0]->instanceId[0];

    echo "instance id: ".$instance_id."\n";

    $n = 1;
    do {
      $xml = $this->executeEC2Query(
        'DescribeInstances',
        array(
          'InstanceId.1' => $instance_id,
        ));

      var_dump($xml);

      $instance = $xml->reservationSet[0]->item[0]->instancesSet[0]->item[0];

      $state = (string)$instance->instanceState[0]->name;

      echo "State = {$state}\n";

      if ($state == 'pending') {
        sleep(min($n++, 15));
      } else if ($state == 'running') {
        break;
      } else {
        // TODO: Communicate this failure.
        $resource->setStatus(DrydockResourceStatus::STATUS_BROKEN);
        $resource->save();
      }
    } while (true);


    $n = 1;
    do {
      $xml = $this->executeEC2Query(
        'DescribeInstanceStatus',
        array(
          'InstanceId' => $instance_id,
        ));

      var_dump($xml);

      $item = $xml->instanceStatusSet[0]->item[0];

      $system_status = (string)$item->systemStatus->status[0];
      $instance_status = (string)$item->instanceStatus->status[0];

      if (($system_status == 'initializing') ||
          ($instance_status == 'initializing')) {
        sleep(min($n++, 15));
      } else if (($system_status == 'ok') &&
                 ($instance_status == 'ok')) {
        break;
      } else {
        // TODO: Communicate this failure.
        $resource->setStatus(DrydockResourceStatus::STATUS_BROKEN);
        $resource->save();
      }
    } while (true);

    // TODO: This is a fuzz factor because sshd doesn't come up immediately
    // once EC2 reports the machine reachable. Validate that SSH is actually
    // responsive.
    sleep(120);

    $resource->setAttributes(
      array(
        'host'          => (string)$instance->dnsName,
        'user'          => 'ec2-user',
        'ssh-keyfile'   => '/Users/epriestley/.ssh/id_ec2w',
      ));
    $resource->setName($resource->getName().' ('.$instance->dnsName.')');
    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    $resource->save();

    return $resource;
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
            'host'        => $resource->getAttribute('host'),
            'user'        => $resource->getAttribute('user'),
            'ssh-keyfile' => $resource->getAttribute('ssh-keyfile'),
          ));
        return $ssh;
    }

    throw new Exception("No interface of type '{$type}'.");
  }

  private function executeEC2Query($action, array $params) {
    $future = new PhutilAWSEC2Future();
    $future->setAWSKeys(
      PhabricatorEnv::getEnvConfig('amazon-ec2.access-key'),
      PhabricatorEnv::getEnvConfig('amazon-ec2.secret-key'));
    $future->setRawAWSQuery($action, $params);
    return $future->resolve();
  }

}
