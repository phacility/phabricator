<?php

final class DrydockApacheWebrootBlueprint
  extends DrydockBlueprint {

  public function getType() {
    return 'webroot';
  }

  public function canAllocateResources() {
    return true;
  }

  public function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {

    $key = Filesystem::readRandomCharacters(12);

    $ports = $resource->getAttribute('ports', array());
    for ($ii = 2000; ; $ii++) {
      if (empty($ports[$ii])) {
        $ports[$ii] = $lease->getID();
        $port = $ii;
        break;
      }
    }
    $resource->setAttribute('ports', $ports);
    $resource->save();

    $host = $resource->getAttribute('host');

    $lease->setAttribute('port', $port);
    $lease->setAttribute('key', $key);
    $lease->save();

    $config = <<<EOCONFIG

Listen *:{$port}
<VirtualHost *:{$port}>
  DocumentRoot  /opt/drydock/webroot/{$key}/
  ServerName {$host}
</VirtualHost>
EOCONFIG;

    $cmd = $this->getInterface($resource, $lease, 'command');
    $cmd->execx(<<<EOSETUP
sudo mkdir -p %s &&
sudo sh -c %s &&
sudo /etc/init.d/httpd restart
EOSETUP
      ,
      "/opt/drydock/webroot/{$key}/",
      csprintf(
        'echo %s > %s',
        $config,
        "/etc/httpd/conf.d/drydock-{$key}.conf"));

    $lease->setAttribute('uri', "http://{$host}:{$port}/");
    $lease->save();
  }

  public function executeAllocateResource(DrydockLease $lease) {

    $resource = $this->newResourceTemplate('Apache');

    $resource->setStatus(DrydockResourceStatus::STATUS_ALLOCATING);
    $resource->save();

    $allocator = $this->getAllocator('host');
    $host = $allocator->allocate();

    $cmd = $host->waitUntilActive()->getInterface('command');

    $cmd->execx(<<<EOINSTALL
(yes | sudo yum install httpd) && sudo mkdir -p /opt/drydock/webroot/
EOINSTALL
);

    $resource->setAttribute('lease.host', $host->getID());
    $resource->setAttribute('host', $host->getResource()->getAttribute('host'));

    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    $resource->save();

    return $resource;
  }

  public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    switch ($type) {
      case 'webroot':
        $iface = new DrydockApacheWebrootInterface();
        $iface->setConfiguration(
          array(
            'uri' => $lease->getAttribute('uri'),
          ));
        return $iface;
      case 'command':
        $host_lease_id = $resource->getAttribute('lease.host');
        $host_lease = id(new DrydockLease())->load($host_lease_id);
        $host_lease->attachResource($host_lease->loadResource());
        return $host_lease->getInterface($type);
    }


    throw new Exception("No interface of type '{$type}'.");
  }

}
