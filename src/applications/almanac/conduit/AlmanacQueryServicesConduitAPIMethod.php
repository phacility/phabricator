<?php

final class AlmanacQueryServicesConduitAPIMethod
  extends AlmanacConduitAPIMethod {

  public function getAPIMethodName() {
    return 'almanac.queryservices';
  }

  public function getMethodDescription() {
    return pht('Query Almanac services.');
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'optional list<id>',
      'phids' => 'optional list<phid>',
      'names' => 'optional list<phid>',
      'serviceClasses' => 'optional list<string>',
    ) + self::getPagerParamTypes();
  }

  public function defineReturnType() {
    return 'list<wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $query = id(new AlmanacServiceQuery())
      ->setViewer($viewer)
      ->needBindings(true);

    $ids = $request->getValue('ids');
    if ($ids !== null) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids !== null) {
      $query->withPHIDs($phids);
    }

    $names = $request->getValue('names');
    if ($names !== null) {
      $query->withNames($names);
    }

    $classes = $request->getValue('serviceClasses');
    if ($classes !== null) {
      $query->withServiceClasses($classes);
    }

    $pager = $this->newPager($request);

    $services = $query->executeWithCursorPager($pager);

    $data = array();
    foreach ($services as $service) {
      $phid = $service->getPHID();

      $properties = $service->getAlmanacProperties();
      $properties = mpull($properties, 'getFieldValue', 'getFieldName');

      $service_bindings = $service->getBindings();
      $service_bindings = array_values($service_bindings);
      foreach ($service_bindings as $key => $service_binding) {
        $service_bindings[$key] = $this->getBindingDictionary($service_binding);
      }

      $data[] = $this->getServiceDictionary($service) + array(
        'bindings' => $service_bindings,
      );
    }

    $results = array(
      'data' => $data,
    );

    return $this->addPagerResults($results, $pager);
  }

  private function getServiceDictionary(AlmanacService $service) {
    return array(
      'id' => (int)$service->getID(),
      'phid' => $service->getPHID(),
      'name' => $service->getName(),
      'uri' => PhabricatorEnv::getProductionURI($service->getURI()),
      'serviceClass' => $service->getServiceClass(),
      'properties' => $this->getPropertiesDictionary($service),
    );
  }

  private function getBindingDictionary(AlmanacBinding $binding) {
    return array(
      'id' => (int)$binding->getID(),
      'phid' => $binding->getPHID(),
      'properties' => $this->getPropertiesDictionary($binding),
      'interface' => $this->getInterfaceDictionary($binding->getInterface()),
    );
  }

  private function getPropertiesDictionary(AlmanacPropertyInterface $obj) {
    $properties = $obj->getAlmanacProperties();
    return (object)mpull($properties, 'getFieldValue', 'getFieldName');
  }

  private function getInterfaceDictionary(AlmanacInterface $interface) {
    return array(
      'id' => (int)$interface->getID(),
      'phid' => $interface->getPHID(),
      'address' => $interface->getAddress(),
      'port' => (int)$interface->getPort(),
      'device' => $this->getDeviceDictionary($interface->getDevice()),
      'network' => $this->getNetworkDictionary($interface->getNetwork()),
    );
  }

  private function getDeviceDictionary(AlmanacDevice $device) {
    return array(
      'id' => (int)$device->getID(),
      'phid' => $device->getPHID(),
      'name' => $device->getName(),
      'properties' => $this->getPropertiesDictionary($device),
    );
  }

  private function getNetworkDictionary(AlmanacNetwork $network) {
    return array(
      'id' => (int)$network->getID(),
      'phid' => $network->getPHID(),
      'name' => $network->getName(),
    );
  }

}
