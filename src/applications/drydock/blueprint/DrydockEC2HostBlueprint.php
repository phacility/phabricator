<?php

final class DrydockEC2HostBlueprint extends DrydockRemoteHostBlueprint {

  public function canAllocateResources() {
    return true;
  }

  public function executeAllocateResource(DrydockLease $lease) {
    $resource = $this->newResourceTemplate('EC2 Host');

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

    $this->log("Started Instance: {$instance_id}");
    $resource->setAttribute('instance.id', $instance_id);
    $resource->save();

    $n = 1;
    do {
      $xml = $this->executeEC2Query(
        'DescribeInstances',
        array(
          'InstanceId.1' => $instance_id,
        ));

      $instance = $xml->reservationSet[0]->item[0]->instancesSet[0]->item[0];

      $state = (string)$instance->instanceState[0]->name;

      if ($state == 'pending') {
        sleep(min($n++, 15));
      } else if ($state == 'running') {
        break;
      } else {
        $this->log("EC2 host reported in unknown state '{$state}'.");

        $resource->setStatus(DrydockResourceStatus::STATUS_BROKEN);
        $resource->save();
      }
    } while (true);

    $this->log('Waiting for Init');

    $n = 1;
    do {
      $xml = $this->executeEC2Query(
        'DescribeInstanceStatus',
        array(
          'InstanceId' => $instance_id,
        ));

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
        $this->log(
          "EC2 system and instance status in bad states: ".
          "'{$system_status}', '{$instance_status}'.");

        $resource->setStatus(DrydockResourceStatus::STATUS_BROKEN);
        $resource->save();
      }
    } while (true);

    $resource->setAttributes(
      array(
        'host'          => (string)$instance->dnsName,
        'user'          => 'ec2-user',
        'ssh-keyfile'   => '/Users/epriestley/.ssh/id_ec2w',
      ));
    $resource->setName($resource->getName().' ('.$instance->dnsName.')');
    $resource->save();

    $this->log('Waiting for SSH');

    // SSH isn't immediately responsive, so wait for it to actually come up.
    $cmd = $this->getInterface($resource, new DrydockLease(), 'command');
    $n = 1;
    do {
      list($err) = $cmd->exec('true');
      if ($err) {
        sleep(min($n++, 15));
      } else {
        break;
      }
    } while (true);

    $this->log('SSH OK');

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
