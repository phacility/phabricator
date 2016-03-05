<?php

final class DrydockAlmanacServiceHostBlueprintImplementation
  extends DrydockBlueprintImplementation {

  private $services;
  private $freeBindings;

  public function isEnabled() {
    $almanac_app = 'PhabricatorAlmanacApplication';
    return PhabricatorApplication::isClassInstalled($almanac_app);
  }

  public function getBlueprintName() {
    return pht('Almanac Hosts');
  }

  public function getBlueprintIcon() {
    return 'fa-server';
  }

  public function getDescription() {
    return pht(
      'Allows Drydock to lease existing hosts defined in an Almanac service '.
      'pool.');
  }

  public function canAnyBlueprintEverAllocateResourceForLease(
    DrydockLease $lease) {
    return true;
  }

  public function canEverAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    $services = $this->loadServices($blueprint);
    $bindings = $this->loadAllBindings($services);

    if (!$bindings) {
      // If there are no devices bound to the services for this blueprint,
      // we can not allocate resources.
      return false;
    }

    return true;
  }

  public function canAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {

    // We will only allocate one resource per unique device bound to the
    // services for this blueprint. Make sure we have a free device somewhere.
    $free_bindings = $this->loadFreeBindings($blueprint);
    if (!$free_bindings) {
      return false;
    }

    return true;
  }

  public function allocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {

    $free_bindings = $this->loadFreeBindings($blueprint);
    shuffle($free_bindings);

    $exceptions = array();
    foreach ($free_bindings as $binding) {
      $device = $binding->getDevice();
      $device_name = $device->getName();

      $binding_phid = $binding->getPHID();

      $resource = $this->newResourceTemplate($blueprint)
        ->setActivateWhenAllocated(true)
        ->setAttribute('almanacDeviceName', $device_name)
        ->setAttribute('almanacServicePHID', $binding->getServicePHID())
        ->setAttribute('almanacBindingPHID', $binding_phid)
        ->needSlotLock("almanac.host.binding({$binding_phid})");

      try {
        return $resource->allocateResource();
      } catch (Exception $ex) {
        $exceptions[] = $ex;
      }
    }

    throw new PhutilAggregateException(
      pht('Unable to allocate any binding as a resource.'),
      $exceptions);
  }

  public function destroyResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {
    // We don't create anything when allocating hosts, so we don't need to do
    // any cleanup here.
    return;
  }

  public function getResourceName(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {
    $device_name = $resource->getAttribute(
      'almanacDeviceName',
      pht('<Unknown>'));
    return pht('Host (%s)', $device_name);
  }

  public function canAcquireLeaseOnResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    return true;
  }

  public function acquireLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    $lease
      ->setActivateWhenAcquired(true)
      ->acquireOnResource($resource);
  }

  public function didReleaseLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    // Almanac hosts stick around indefinitely so we don't need to recycle them
    // if they don't have any leases.
    return;
  }

  public function destroyLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    // We don't create anything when activating a lease, so we don't need to
    // throw anything away.
    return;
  }

  public function getType() {
    return 'host';
  }

  public function getInterface(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    $viewer = PhabricatorUser::getOmnipotentUser();

    switch ($type) {
      case DrydockCommandInterface::INTERFACE_TYPE:
        $credential_phid = $blueprint->getFieldValue('credentialPHID');
        $binding_phid = $resource->getAttribute('almanacBindingPHID');

        $binding = id(new AlmanacBindingQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($binding_phid))
          ->executeOne();
        if (!$binding) {
          throw new Exception(
            pht(
              'Unable to load binding "%s" to create command interface.',
              $binding_phid));
        }

        $interface = $binding->getInterface();

        return id(new DrydockSSHCommandInterface())
          ->setConfig('credentialPHID', $credential_phid)
          ->setConfig('host', $interface->getAddress())
          ->setConfig('port', $interface->getPort());
    }
  }

  protected function getCustomFieldSpecifications() {
    return array(
      'almanacServicePHIDs' => array(
        'name' => pht('Almanac Services'),
        'type' => 'datasource',
        'datasource.class' => 'AlmanacServiceDatasource',
        'datasource.parameters' => array(
          'serviceTypes' => $this->getAlmanacServiceTypes(),
        ),
        'required' => true,
      ),
      'credentialPHID' => array(
        'name' => pht('Credentials'),
        'type' => 'credential',
        'credential.provides' =>
          PassphraseSSHPrivateKeyCredentialType::PROVIDES_TYPE,
        'credential.type' =>
          PassphraseSSHPrivateKeyTextCredentialType::CREDENTIAL_TYPE,
      ),
    );
  }

  private function loadServices(DrydockBlueprint $blueprint) {
    if (!$this->services) {
      $service_phids = $blueprint->getFieldValue('almanacServicePHIDs');
      if (!$service_phids) {
        throw new Exception(
          pht(
            'This blueprint ("%s") does not define any Almanac Service PHIDs.',
            $blueprint->getBlueprintName()));
      }

      $viewer = PhabricatorUser::getOmnipotentUser();
      $services = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withPHIDs($service_phids)
        ->withServiceTypes($this->getAlmanacServiceTypes())
        ->needBindings(true)
        ->execute();
      $services = mpull($services, null, 'getPHID');

      if (count($services) != count($service_phids)) {
        $missing_phids = array_diff($service_phids, array_keys($services));
        throw new Exception(
          pht(
            'Some of the Almanac Services defined by this blueprint '.
            'could not be loaded. They may be invalid, no longer exist, '.
            'or be of the wrong type: %s.',
            implode(', ', $missing_phids)));
      }

      $this->services = $services;
    }

    return $this->services;
  }

  private function loadAllBindings(array $services) {
    assert_instances_of($services, 'AlmanacService');
    $bindings = array_mergev(mpull($services, 'getBindings'));
    return mpull($bindings, null, 'getPHID');
  }

  private function loadFreeBindings(DrydockBlueprint $blueprint) {
    if ($this->freeBindings === null) {
      $viewer = PhabricatorUser::getOmnipotentUser();

      $pool = id(new DrydockResourceQuery())
        ->setViewer($viewer)
        ->withBlueprintPHIDs(array($blueprint->getPHID()))
        ->withStatuses(
          array(
            DrydockResourceStatus::STATUS_PENDING,
            DrydockResourceStatus::STATUS_ACTIVE,
            DrydockResourceStatus::STATUS_BROKEN,
            DrydockResourceStatus::STATUS_RELEASED,
          ))
        ->execute();

      $allocated_phids = array();
      foreach ($pool as $resource) {
        $allocated_phids[] = $resource->getAttribute('almanacBindingPHID');
      }
      $allocated_phids = array_fuse($allocated_phids);

      $services = $this->loadServices($blueprint);
      $bindings = $this->loadAllBindings($services);

      $free = array();
      foreach ($bindings as $binding) {
        // Don't consider disabled bindings to be available.
        if ($binding->getIsDisabled()) {
          continue;
        }

        if (empty($allocated_phids[$binding->getPHID()])) {
          $free[] = $binding;
        }
      }

      $this->freeBindings = $free;
    }

    return $this->freeBindings;
  }

  private function getAlmanacServiceTypes() {
    return array(
      AlmanacDrydockPoolServiceType::SERVICETYPE,
    );
  }


}
